# Permissions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permission-based authorization for the package: one or two definitions classes, `permissions:sync`, a super-admin, `make:crud --permissions` / `--owned`, tenant isolation, generated tests.

**Architecture:** New `src/Permissions/` namespace holds the pure logic (definitions locator, pattern expansion, sync, definitions-file editing, tenant cache isolation). Commands and `MakeCrudCommand` call into it. spatie/laravel-permission does storage and checks; generated policies call `checkPermissionTo()`; the package's `Gate::before` grants the super-admin.

**Tech Stack:** PHP 8.3, Laravel 12/13, spatie/laravel-permission `^8.0` (optional), stancl/tenancy 3, Pest 4, Testbench 10/11.

**Spec:** `docs/superpowers/specs/2026-11-03-permissions-design.md`

## Global Constraints

- spatie is optional: composer `suggest` + `require-dev` (`"spatie/laravel-permission": "^8.0"`); no `src/` code may hard-reference a spatie class outside a `class_exists()` guard or a code path only reached after the guard.
- Every failure mode denies: missing sync, missing role, missing permission row, typo → 403/404, never access.
- Permission names: `<group>.<ability>`; group = `CrudNames::uri` (kebab plural, e.g. `products`); ability `view|create|update|delete`, plus `any` with `--owned`. Regex for both parts: `^[a-z0-9]+(-[a-z0-9]+)*$`.
- Super-admin role name: `config('blueprint.super_admin')`, default `'super-admin'`. Guard: `config('blueprint.permission_guard')`, default `'web'`.
- Definitions: `Permissions` (all profiles; tenant side in tenant-saas) and `CentralPermissions` (tenant-saas only, central side). The two never mix.
- Generated tests must pass straight after generation without editing `roles()`.
- Wildcards (`*`, `<group>.*`) are expanded at sync time; spatie's wildcard mode stays off.
- Follow existing patterns: refusals via `$this->refuse()`, idempotent setup lines (`created …` / `already set up: …`), stub validation guards before writing.
- `composer check` green after every task. `bin/e2e` green at the end.

## Review Focus

1. **Old published stubs with `--owned`** — a user who published `controller.stub`/`test.stub` before this release lacks the new placeholders; the owner scoping must not be silently dropped → refuse like the feature-gate guard (Task 7 test `refuses an old controller stub that drops owner scoping`).
2. **Sync never run** — a CRUD with `--permissions` in an app that never ran `permissions:sync`: every signed-in non-super-admin gets 403, not a 500 from `PermissionDoesNotExist` (Task 6 test `denies instead of erroring when permissions were never synced`).
3. **Same process, two tenants** — a queue worker or test that initializes tenant A then tenant B must not reuse A's permission collection (Task 4 test `does not reuse one tenant's permissions in another`).
4. **Super-admin role name listed in `roles()`** — refuse at sync with a clear message rather than giving it a partial permission set (Task 1 test `rejects the super-admin role in roles()`).
5. **Owner changed on update by an `.any` holder** — updating someone else's record must keep the original `user_id` (Task 7 test `keeps the owner when an any-holder updates`).

---

### Task 1: Definitions, config and pattern expansion

**Files:**
- Modify: `composer.json` (suggest + require-dev), `config/blueprint.php`
- Modify: `src/Flags/Definitions.php` (add `permissions()`, `centralPermissions()`)
- Create: `src/Permissions/PermissionSet.php`, `src/Permissions/InvalidPermissionDefinition.php`
- Test: `tests/Unit/PermissionSetTest.php`

**Interfaces:**
- Produces: `Definitions::permissions(): string` (config `blueprint.permissions`, else `<shared ns>\Permissions\Permissions`), `Definitions::centralPermissions(): string` (config `blueprint.central_permissions`, else `<shared ns>\Permissions\CentralPermissions`).
- Produces: `final readonly class PermissionSet { public static function fromClass(string $class): self; /** @param array<string, list<string>> $groups @param array<string, list<string>> $roles */ public static function from(array $groups, array $roles): self; /** @return list<string> */ public function permissions(): array; /** @return array<string, list<string>> role => expanded sorted permissions */ public function roles(): array; }` — throws `InvalidPermissionDefinition` (extends `RuntimeException`).
- Config keys added (with inline comments like the existing ones): `'permissions' => null`, `'central_permissions' => null`, `'super_admin' => 'super-admin'`, `'permission_guard' => 'web'`.

- [ ] **Step 1: Write failing tests** in `tests/Unit/PermissionSetTest.php`:
  - `expands groups into names`: `from(['products' => ['view','update']], [])->permissions()` === `['products.update','products.view']` (sorted).
  - `expands wildcards`: roles `['admin' => ['*'], 'editor' => ['products.*'], 'viewer' => ['products.view','orders.view']]` with groups products/orders → admin gets all, editor gets products only, viewer exact two.
  - `rejects a pattern that matches nothing`: role `['editor' => ['prodcts.view']]` throws with message containing `editor` and `prodcts.view`.
  - `rejects a group wildcard for an unknown group`: `['x' => ['nope.*']]` throws.
  - `rejects invalid names`: group `'Products'`, ability `'Update'`, `'a..b'` each throw.
  - `rejects the super-admin role in roles()`: with config `super_admin` = `'super-admin'`, roles `['super-admin' => ['*']]` throws with message containing `built in`.
  - `reads a definitions class`: `fromClass()` on a fixture `tests/Fixtures/TestPermissions.php` (`groups()` / `roles()` static methods) round-trips; `fromClass('Missing\\Class')` throws.
- [ ] **Step 2: Run** `vendor/bin/pest tests/Unit/PermissionSetTest.php` → FAIL (class not found).
- [ ] **Step 3: Implement** config keys, `Definitions` methods (reuse `resolve()`), `PermissionSet`. An empty role list (`'member' => []`) is valid.
- [ ] **Step 4: Run** the test file → PASS; `composer check` → green.
- [ ] **Step 5: Commit** `feat(permissions): definitions, config and pattern expansion`.

### Task 2: Sync service, `permissions:sync` (single database), super-admin gate

**Files:**
- Create: `src/Permissions/PermissionSync.php`, `src/Permissions/SyncResult.php`, `src/Permissions/PermissionsDetector.php`, `src/Console/PermissionsSyncCommand.php`
- Modify: `src/BlueprintServiceProvider.php` (register command; `Gate::before`)
- Test: `tests/Feature/PermissionsSyncTest.php`, `tests/Feature/SuperAdminTest.php`, fixture `tests/Fixtures/User.php` gains `HasRoles` only if it doesn't break existing tests (otherwise add `tests/Fixtures/RoleUser.php`)

**Interfaces:**
- Produces: `PermissionsDetector { public function spatie(): bool; }` — `class_exists(\Spatie\Permission\PermissionServiceProvider::class) && app()->providerIsLoaded(...)`.
- Produces: `PermissionSync { public function run(PermissionSet $set, bool $prune): SyncResult; }` — on the current default connection; creates missing permissions/roles (guard from config), `syncPermissions()` for each defined role, creates the super-admin role, lists stale (not in set; never the super-admin) and deletes them only when `$prune`; finally `app(PermissionRegistrar::class)->forgetCachedPermissions()`.
- Produces: `final readonly class SyncResult { public int $permissionsAdded; public int $rolesAdded; /** @var list<string> */ public array $stale; public bool $pruned; }`.
- Produces: command `permissions:sync {--prune} {--tenant=*} {--central}` (tenant options implemented in Task 4; here they refuse outside tenant-saas with `--tenant and --central only apply to the tenant-saas profile.`).

- [ ] **Step 1: Write failing tests** (`PermissionsSyncTest`, testbench with spatie provider + its migration run in `beforeEach`):
  - `creates permissions, roles and the super-admin` (counts in DB; role `editor` has exactly its permissions).
  - `is idempotent` (second run: 0 added, same rows).
  - `makes the file authoritative for named roles` (give `editor` an extra permission by hand → after sync it's gone).
  - `lists stale entries without deleting them` (output contains `stale: legacy.view`; row still exists) and `prunes with --prune`.
  - `fails on a definition error and changes nothing` (typo role → exit 1, message names role and pattern, no rows written).
  - `refuses without spatie` (simulate detector false via container binding → message `composer require spatie/laravel-permission`).
  - `refuses tenant options outside tenant-saas`.
- [ ] **Step 2: Write failing tests** (`SuperAdminTest`): super-admin user passes `Gate::allows('anything')` and a policy that returns false; normal user unaffected (`Gate::before` returns `null`, so a policy returning true still allows); user model without `hasRole` method doesn't error.
- [ ] **Step 3: Run** both → FAIL.
- [ ] **Step 4: Implement.** Sync runs inside a DB transaction so a mid-way failure writes nothing. `Gate::before` registered in `boot()` only when `PermissionsDetector::spatie()`; returns `true` if `method_exists($user, 'hasRole') && $user->hasRole(config('blueprint.super_admin'), config('blueprint.permission_guard'))`, else `null`.
- [ ] **Step 5: Keep the docs-site test green:** change the expected command count in `tests/Unit/DocsSiteTest.php` from 13 to 14, and add a short `id="permissions-sync"` card plus `<code>` entries for the four new config keys to `site/reference.html` and `site/ar/reference.html` (Task 8 expands them).
- [ ] **Step 6: Run** both test files → PASS; `composer check` green.
- [ ] **Step 7: Commit** `feat(permissions): permissions:sync and super-admin`.

### Task 3: `engineering:install --permissions`

**Files:**
- Create: `src/Permissions/PermissionSetup.php`, `src/Permissions/UserModelEditor.php`, `stubs/permissions-definitions.stub` (namespace + class name placeholders: `{{ namespace }}`, `{{ class }}`)
- Modify: `src/Console/InstallCommand.php` (add `{--permissions : Set up roles and permissions (needs spatie/laravel-permission)}`)
- Test: `tests/Feature/PermissionInstallTest.php`, `tests/Unit/UserModelEditorTest.php`

**Interfaces:**
- Produces: `PermissionSetup { /** @return list<string> */ public function run(Profile $profile): array; }` — creates `<shared>/Permissions/Permissions.php`, and in tenant-saas `<shared>/Permissions/CentralPermissions.php`; copies spatie's `create_permission_tables` migration stub (located via `ReflectionClass(PermissionServiceProvider::class)`, verify the path in the installed v8) to `database/migrations/<date>_000300_create_permission_tables.php` (all profiles; in tenant-saas this is the central copy) and, in tenant-saas, also `database/migrations/tenant/…`; edits the User model. Idempotent: existing file/migration (glob `*_create_permission_tables.php` per folder) → `already set up: …`.
- Produces: `UserModelEditor { public static function addHasRoles(string $source): ?string; }` — returns source unchanged if it already uses `HasRoles`; adds `use Spatie\Permission\Traits\HasRoles;` import (sorted into the import block) and appends `HasRoles` to the first in-class `use A, B;` trait line; returns `null` when there is no such line.
- Stub content: the two methods and docblocks from the spec's "Definitions classes" section, commented examples, with `Permissions` vs `CentralPermissions` docblock wording differing via a `{{ side }}` placeholder (`tenant` / `central` / `app`).

- [ ] **Step 1: Write failing tests:**
  - `UserModelEditorTest`: Laravel 12 and 13 stock `User.php` (copy both into `tests/Fixtures/user-models/`) → result contains `use HasFactory, Notifiable, HasRoles;` (or the L13 equivalent line) and the import; already-present → unchanged; no trait line → `null`; result passes `php -l`.
  - `PermissionInstallTest`: `--profile=modular --permissions` creates definitions + migration + edits user; re-run → only `already set up` lines; tenant-saas (in `tests/Tenancy/PermissionInstallTenancyTest.php`) creates both definitions and both migrations; without spatie → refuses with `composer require spatie/laravel-permission` and writes nothing; uneditable User → prints `Add "use HasRoles;" to App\Models\User` and still succeeds; existing definitions file is never overwritten.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement**; the install summary lists `permissions:sync` as the next step.
- [ ] **Step 4: Run** → PASS; `composer check` green.
- [ ] **Step 5: Commit** `feat(permissions): engineering:install --permissions`.

### Task 4: Tenancy — per-side sync, tenant:create step, cache isolation

**Files:**
- Create: `src/Permissions/TenantPermissionCache.php`
- Modify: `src/Console/PermissionsSyncCommand.php`, `src/Tenancy/TenantSetup.php` (new step `Sync permissions` after `Create default features`), `src/BlueprintServiceProvider.php` (listen to `TenancyInitialized` / `TenancyEnded` when tenancy + spatie active)
- Test: `tests/Tenancy/PermissionsTenancyTest.php`

**Interfaces:**
- Consumes: `PermissionSet::fromClass()`, `PermissionSync::run()`, `Definitions::permissions()/centralPermissions()`.
- Produces: `TenantPermissionCache { public function initialized(TenancyInitialized $e): void; public function ended(TenancyEnded $e): void; }` — sets spatie's cache key to `spatie.permission.cache.tenant.<tenant id>` (central: the configured default) and clears the registrar's in-memory permission collection (check the v8 API names: cache key property/config and `clearPermissionsCollection()` or equivalent).
- Sync command in tenant-saas: no options → all tenants with `Permissions`; `--tenant=a --tenant=b` → those (validate all first, like `features:sync`); `--central` → central DB with `CentralPermissions`; `--central` with `--tenant` → refuse. Per-tenant failures reported, exit 1, others still synced.

- [ ] **Step 1: Write failing tests:**
  - `syncs tenant definitions into every tenant and never the central database` (central `permissions` table has none of `TestPermissions` names).
  - `syncs central definitions only into the central database` (`--central`; tenant DB has no central names).
  - `refuses --central with --tenant`; `refuses an unknown tenant before syncing any`.
  - `tenant:create syncs permissions` (new tenant has rows; re-run says `already`/idempotent) and `skips when permissions aren't set up`.
  - `does not reuse one tenant's permissions in another`: grant `products.view` to a user in tenant A, initialize tenant B (same user id exists there, no permission) → `checkPermissionTo('products.view')` false; back to A → true.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** → PASS; `composer check` green.
- [ ] **Step 5: Commit** `feat(permissions): tenant and central sync, tenant cache isolation`.

### Task 5: Testing helper for generated tests

**Files:**
- Create: `src/Testing/InteractsWithPermissions.php`
- Test: `tests/Feature/InteractsWithPermissionsTest.php`

**Interfaces:**
- Produces trait methods: `protected function grantPermissions(Authenticatable $user, string ...$permissions): void` — creates the given permissions if missing (configured guard), creates role `test-role-<first 8 chars of sha1 of the sorted names>` holding exactly them, assigns it to the user. `protected function makeSuperAdmin(Authenticatable $user): void` — creates the super-admin role if missing and assigns it.
- It doesn't read definitions classes and works on whichever database is current, so tenant and central tests use it the same way.

- [ ] **Step 1: Write failing tests:** granted user passes `checkPermissionTo` for granted names only; same set twice reuses one role; `makeSuperAdmin` passes `Gate::allows('x')`.
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement.** **Step 4: Run** → PASS; `composer check`.
- [ ] **Step 5: Commit** `feat(permissions): testing helper`.

### Task 6: `make:crud --permissions`

**Files:**
- Create: `src/Permissions/DefinitionsFile.php`, `stubs/policy-permissions.stub`
- Modify: `src/Console/MakeCrudCommand.php` (option, refusals, render values, side matching, guard, messages), `stubs/test.stub`, `stubs/test-tenant.stub`, `stubs/test-web.stub`, `stubs/test-web-tenant.stub` (new placeholders `{{ permissionSetup }}`, `{{ permissionTest }}`), `stubs/README.md`
- Test: `tests/Feature/MakeCrudPermissionsTest.php`, `tests/Unit/DefinitionsFileTest.php`, `tests/Tenancy/TenantCrudPermissionsTest.php`

**Interfaces:**
- Consumes: `PermissionsDetector`, `Definitions::permissions()/centralPermissions()`, `InteractsWithPermissions`.
- Produces: `DefinitionsFile { /** @param list<string> $abilities */ public static function addGroup(string $source, string $group, array $abilities): string; public static function hasGroup(string $source, string $group): bool; }` — inserts `'<group>' => ['view', 'create', 'update', 'delete'],` as the last entry of the array returned by `groups()`; throws `InvalidPermissionDefinition` when `groups()`'s `return [ … ];` can't be found (command then refuses and prints the line to add by hand).
- Policy stub: `final class {{ entity }}Policy` with `private const GROUP = '{{ uri }}';` and each method `return $user->checkPermissionTo(self::GROUP.'.<ability>');` (`viewAny`/`view` → `view`). `checkPermissionTo` returns false for unknown permissions — that is the Review Focus #2 behavior.
- Option: `{--permissions : Policies check named permissions (needs engineering:install --permissions)}`.
- Which file: `CentralPermissions` when module kind is `central`, else `Permissions`.
- Generated test setup (`{{ permissionSetup }}`): `    $this->grantPermissions($this->user, '<g>.view', '<g>.create', '<g>.update', '<g>.delete');\n`; `uses()` gains `InteractsWithPermissions::class`.
- Generated extra tests (`{{ permissionTest }}`, API and web variants): `it('denies users without the permission')` — fresh user, each endpoint → 403 (web: GET index 403); `it('lets a super-admin in')` — fresh user + `makeSuperAdmin`, index → 200.
- Side matching: existing policy contains `checkPermissionTo(` ⇔ permissions mode; mismatch → refuse `The existing {Entity} CRUD uses permissions; add --permissions.` / `…does not use permissions; leave --permissions out.`
- Guard: with `--permissions`, refuse if the rendered policy lacks `checkPermissionTo(` or a rendered test lacks `grantPermissions(` (message pattern as the feature guard).
- Messages: replace the "lets every signed-in user do everything" note with `Next: add '<g>.*' (or single permissions) to a role in <Definitions file>, then php artisan permissions:sync` when `--permissions`; without the flag but with a definitions class present, add `Tip: --permissions makes the policy check named permissions.`

- [ ] **Step 1: Write failing tests:**
  - `DefinitionsFileTest`: adds to empty and non-empty `groups()`; `hasGroup` true afterwards; result `php -l` clean; missing `groups()` throws.
  - `MakeCrudPermissionsTest`: generates the permission policy (`private const GROUP = 'orders';`, four `checkPermissionTo` calls); adds `orders` group to the definitions file once (second CRUD in same file, group untouched); refuses when not set up (no spatie / no definitions class) with the fix command; refuses side mismatch both ways; refuses a published policy stub without `checkPermissionTo(`; generated test file lints and contains `grantPermissions(`; prints the next-step line.
  - Runs the generated CRUD end to end (pattern of `CrudEndToEndTest`): permitted user 200/201/204; user without permission 403; never-synced app user 403 (`denies instead of erroring when permissions were never synced`); super-admin 200.
  - `TenantCrudPermissionsTest`: tenant module writes to `Permissions`, `--central` module to `CentralPermissions`; generated tenant test passes end to end.
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement.** **Step 4: Run** → PASS; `composer check`.
- [ ] **Step 5: Commit** `feat(permissions): make:crud --permissions`.

### Task 7: `make:crud --owned`

**Files:**
- Create: `stubs/policy-owned.stub`, `stubs/query-owned.stub`, `stubs/crud-create-action-owned.stub`
- Modify: `src/Console/MakeCrudCommand.php`, `stubs/controller.stub`, `stubs/web-controller.stub` (placeholders `{{ indexParameters }}`, `{{ listArguments }}`, `{{ createArguments }}`), `stubs/migration.stub` unchanged (owner column passed in `columns`), `stubs/factory.stub` unchanged (owner line passed in `definition`), `stubs/model.stub` (`{{ relations }}` placeholder), the four test stubs (`{{ factoryOwner }}`), `stubs/README.md`
- Test: `tests/Feature/MakeCrudOwnedTest.php`, tenant case in `tests/Tenancy/TenantCrudPermissionsTest.php`

**Interfaces:**
- Option: `{--owned : Records belong to the user who creates them (needs --permissions)}`; without `--permissions` → refuse `--owned needs --permissions.`
- Group gains `any`: abilities `['view','create','update','delete','any']`.
- Migration column line: `$table->foreignId('user_id')->constrained()->cascadeOnDelete();`. Factory: `'user_id' => User::factory(),`. Model: `public function user(): BelongsTo` (`@return BelongsTo<User, $this>`); `user_id` is NOT fillable.
- `query-owned.stub`: `handle(User $user, int $perPage = 15): LengthAwarePaginator` — `->when(! $user->checkPermissionTo('<g>.any'), fn ($q) => $q->whereBelongsTo($user))`.
- `crud-create-action-owned.stub`: `handle({{ entity }}Data $data, User $owner): {{ entity }}` — new model from `$data->toArray()`, `->user()->associate($owner)`, save, in a transaction.
- Controllers in owned mode: `indexParameters` = `Request $request, List{{ plural }} $query`, `listArguments` = `$request->user()`, `createArguments` = `, $request->user()`; otherwise `List{{ plural }} $query`, `''`, `''`. Add `Illuminate\Http\Request` import only in owned mode.
- `policy-owned.stub`: `view/update/delete(User $user, Model $m): Response` — no `<g>.<ability>` → `Response::deny()`; has it but not owner and not `<g>.any` → `Response::denyAsNotFound()`; else `Response::allow()`. `viewAny`/`create` as in Task 6.
- Test stubs: `{{ factoryOwner }}` = `->for($this->user)` in owned mode (so "shows/updates/deletes a X" act on the user's own record), `''` otherwise. Extra owned tests: `hides other users' records` (show/update/delete → 404, list count 0); `lets an any-holder act on every record` (grant also `<g>.any` → show 200, list count 1); `keeps the owner when an any-holder updates` (update by any-holder → `user_id` unchanged).
- Guard (Review Focus #1): in owned mode refuse unless rendered controller(s) contain `->handle($request->user())` and `, $request->user())` in store, rendered query contains `whereBelongsTo(`, rendered policy contains `denyAsNotFound(`.
- Side matching: existing policy contains `denyAsNotFound(` ⇔ owned; mismatch refused like Task 6.

- [ ] **Step 1: Write failing tests** for each bullet above plus `refuses an old controller stub that drops owner scoping` (publish a `controller.stub` without the new placeholders → exit 1, nothing written) and an end-to-end run of the generated owned CRUD (owner 200; other user 404; any-holder 200; list scoping).
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement.** **Step 4: Run** → PASS; also re-run `tests/Feature/MakeCrudCommandTest.php` and `MakeCrudWebTest.php` to prove non-owned output is byte-identical to before (snapshot an existing generated controller/test before changing stubs). `composer check`.
- [ ] **Step 5: Commit** `feat(permissions): make:crud --owned`.

### Task 8: Docs, kit, site, stale lines, e2e

**Files:**
- Create: `docs/PERMISSIONS.md`
- Modify: `docs/PHASE-0.md` (link PERMISSIONS.md; replace stale "Remaining: Phase 9/10" with the README wording), `docs/ARCHITECTURE.md` (Policy row), `docs/TENANCY.md` (spatie tables per database, cache isolation), `docs/NAMING.md` (permission names), `README.md`, `rules.md`, `CHANGELOG.md`, `UPGRADE.md` (published-stub note for `--owned`), `kit/skills/laravel-engineering/SKILL.md` (permissions section; fix stale "Phases 0–8" status), `kit/skills/leb-generate-crud/SKILL.md`, `kit/rules/cursor/laravel-engineering.mdc`, `site/reference.html`, `site/ar/reference.html` (full cards), `site/crud.html` + `site/ar/crud.html` (flags), `bin/e2e`
- Test: existing `DocsSiteTest`, `PackageFilesTest`

- [ ] **Step 1:** Write `docs/PERMISSIONS.md` from the spec (purpose, storage per profile, definitions classes, names, sync, super-admin, `--permissions`, `--owned` rule table, tenancy, testing the app's real roles with an example). Then the other doc edits. If any task added user-facing text (pages, flash or API messages), add it to `lang/ar.json`; console output stays English like the rest of the package.
- [ ] **Step 2: Update `bin/e2e`:** `new_app` gets `composer require spatie/laravel-permission:^8.0`; every profile installs with `--permissions`; `basic`: `permissions:sync` + `php artisan test`; `modular`/`saas`: `make:crud Shop Product … --permissions --owned`, `migrate`, `permissions:sync`; `tenant-saas`: tenant CRUD `--permissions --owned`, central `make:crud Admin Plan … --permissions`, `migrate`, `tenants:migrate`, `permissions:sync`, `permissions:sync --central`, then `php artisan test`.
- [ ] **Step 3: Run** `composer check` → green; `bin/e2e` → all four ✓.
- [ ] **Step 4:** `./bin/leb-install <kept e2e app> --link-agents` and confirm the new docs/skills land.
- [ ] **Step 5: Commit** `docs(permissions): specs, README, kit, site; e2e covers permissions`.
