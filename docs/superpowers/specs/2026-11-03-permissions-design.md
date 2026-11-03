# Permissions — design

Status: draft for approval (2026-11-03); builder check done

## Why

`make:crud` generates policies that return `true` for every signed-in user. The command prints a warning, but an app that ships the scaffold unchanged lets every user edit and delete every record. That is the largest gap between the generated code and expert-level code.

The package must not decide who an app's users are. Every app built with it has different roles. So the package supplies the building blocks (permission names, a roles file, sync, a super-admin, ownership, tests) and safe defaults; each app fills in its own roles.

## Goals

- Generated policies check named permissions instead of returning `true`.
- An app declares its roles and their permissions in one code file; a command writes them to the database. Nobody creates permission rows by hand.
- A built-in super-admin role passes every check.
- Deny by default: a permission no role holds is denied, so a new entity is locked until access is granted.
- Optional per-entity ownership: users only see and change their own records.
- Works in every profile (`basic`, `modular`, `saas`, `tenant-saas`). In `tenant-saas`, roles and permissions are isolated per tenant and separate from the central admin side.
- Generated tests prove the rules.

## Non-goals

- Picking roles for the app. The package ships an example roles file, not a role model.
- A UI to manage roles or assign them to users.
- API authentication (`auth:sanctum` wiring). Separate topic.
- Field-level or row-level rules beyond ownership.
- Replacing Laravel policies or Gates. Permissions feed the generated policies; policies stay the single place of authorization (ARCHITECTURE.md: Policy = check user permissions).

## Anti-goals (failure even if it works)

- Any path where a missing role, a missing sync, or a typo grants access. Every failure mode must deny.
- Permission names scattered as string literals across the app (same rule as features: names live in one definitions class).
- One tenant's roles or cached permissions affecting another tenant or the central side.
- Making spatie a hard dependency for apps that don't use permissions.

## Decided

- **Base: `spatie/laravel-permission`**, as an optional dependency (composer `suggest`, `require-dev` for the package tests), the same way `stancl/tenancy` is handled. Recommended by the assistant; the user accepted.
- **Generic, app-agnostic** (user's direction): the package never assumes specific roles.
- **tenant-saas: separate definitions for the central side and the tenants** (user's decision). Central roles live in their own file, so neither side's roles or permissions can appear in the other's database.
- **The shape played back and confirmed by the user:** generated permission names per entity, one roles definitions file per app, `permissions:sync`, built-in super-admin with a configurable name, deny by default, per-database roles in `tenant-saas`, generated allow/deny tests.

## Design

### Setup

- `php artisan engineering:install --permissions` (combinable with `--profile`) sets permissions up. It refuses with an install hint when `spatie/laravel-permission` isn't installed.
- It publishes/creates spatie's migration: an app migration in `basic`/`modular`/`saas`; in `tenant-saas` both a central migration (central admins) and a tenant migration (`database/migrations/tenant`).
- It creates the definitions class `app/Shared/Permissions/Permissions.php` (config `blueprint.permissions`, null → `<shared_path>/Permissions/Permissions.php`), never overwriting an existing one.
- In `tenant-saas` it also creates `app/Shared/Permissions/CentralPermissions.php` (config `blueprint.central_permissions`, same null rule), with the same shape. `Permissions` holds the tenant side; `CentralPermissions` holds the central admin side.
- It adds spatie's `HasRoles` trait to `App\Models\User` when the file can be edited safely (via `PhpSource`); otherwise it prints the exact line to add and refuses nothing.
- Config: `blueprint.permissions` (definitions class), `blueprint.super_admin` (role name, default `super-admin`), `blueprint.permission_guard` (default `web`).
- Re-running is idempotent.

### Definitions classes (the app's single source of truth)

`Permissions` below; in `tenant-saas`, `CentralPermissions` has the same two methods for the central side. The two never mix: tenant databases only ever receive `Permissions`, the central database only `CentralPermissions`.

```php
final class Permissions
{
    /**
     * Permission groups: prefix => abilities. make:crud --permissions adds entries here.
     *
     * @return array<string, list<string>>
     */
    public static function groups(): array
    {
        return [
            // 'products' => ['view', 'create', 'update', 'delete'],
        ];
    }

    /**
     * Roles and their permissions. Patterns: 'products.view', 'products.*', '*'.
     * The super-admin role (config blueprint.super_admin) is implicit and passes every check.
     *
     * @return array<string, list<string>>
     */
    public static function roles(): array
    {
        return [
            // 'admin' => ['*'],
            // 'editor' => ['products.view', 'products.update'],
        ];
    }
}
```

- Permission names are `<group>.<ability>`, e.g. `products.update`. The group is the CRUD's URI (`CrudNames::uri`).
- Wildcards are expanded at sync time into explicit permissions. spatie's own wildcard mode is not used.
- A role pattern that matches no defined permission is a sync error (catches typos), not a silent no-op.

### `permissions:sync`

- Creates missing permissions and roles from the definitions class, and sets each defined role's permissions to exactly what the file says (the file is authoritative for roles it names).
- Never deletes permissions or roles that aren't in the file unless `--prune` is passed; without it, lists them as stale.
- Clears spatie's permission cache after writing.
- `tenant-saas`: `--tenant=*` like `features:sync` (all tenants when omitted) syncs `Permissions` into tenant databases; `--central` syncs `CentralPermissions` into the central database. Typo and stale checks run per side. `tenant:create` runs it for the new tenant.
- Exit code non-zero on definition errors, with the offending role/pattern named.

### Super-admin

- When permissions are set up, the package registers a `Gate::before` that returns `true` for users with the super-admin role and `null` otherwise (never `false`, so it can't block other checks).
- `permissions:sync` creates the role. Assigning it to a user is the app's job; the docs show `$user->assignRole(...)` and a seeder example.

### `make:crud --permissions`

- Refuses when permissions aren't set up (no definitions class or no spatie), with the command to fix it.
- Adds the entity's group (`view`, `create`, `update`, `delete`) to `groups()` in the definitions file, unless already there: `CentralPermissions` for a `--central` module in `tenant-saas`, `Permissions` otherwise.
- Generates a policy where each method checks its permission: `viewAny`/`view` → `<group>.view`, `create` → `.create`, `update` → `.update`, `delete` → `.delete`.
- Generated permission names are referenced in one place in the policy (no string literals spread through controllers/views).
- Prints the next step: add the group to a role in `roles()` and run `permissions:sync`.
- Adding a side (`--web` to an existing `--api` CRUD) must match the existing CRUD's permissions mode, like `--feature` does today.
- Without `--permissions`, `make:crud` behaves as today. When permissions are set up and the flag is omitted, it prints a one-line reminder.

### `--owned` (ownership, opt-in per entity)

- Requires `--permissions`.
- Adds a `user_id` foreign key (constrained, cascade on delete) to the migration, sets it from the signed-in user in the create action (never from request input), and adds the relation to the model. The factory creates a user for it.
- Adds one extra permission to the group: `<group>.any`. It only **widens** the other permissions to every record; it grants nothing alone (assistant's call, deny-safe). Policy rule: `can('<group>.<ability>') && (owner || can('<group>.any'))`. Example: `products.any` + `products.view` without `products.delete` → sees every record, deletes none. `.any` without `.view` → sees nothing.
- `List<Plural>` returns only the user's records unless they hold `.any` (and needs `.view` either way).
- On a single record the policy denies non-owners as not found (`Response::denyAsNotFound()`), so users can't probe which records exist — consistent with how guests are handled today.

### Tenancy

- `tenant-saas`: spatie's tables exist in each tenant database and in the central database. Tenant modules check tenant-database permissions; central modules check central ones. Because users are already per database (TENANCY.md), spatie's `teams` mode isn't needed. A test must prove a central permission never exists in a tenant database and the reverse.
- spatie's permission cache must be per tenant. This relies on stancl's cache bootstrapper with a taggable store (already required by TENANCY.md); a test must prove tenant A's cached permissions never apply to tenant B.
- `saas`/`basic`/`modular`: one set in the app database.

### Generated tests (`--permissions`, `--owned`)

Added to the existing per-entity Pest file. The generated tests must pass straight after `make:crud` with no edits to `roles()` (the package treats a red generated suite as a defect). So they sync the permissions from `groups()` and build their own test role granting exactly the permission each case needs; they don't depend on the app's real roles (assistant's call). Testing the app's real role matrix is the app's job; the docs show how.

- A user without the permission gets 403 on each endpoint (or page).
- A user with the permission, via the test role, succeeds.
- A super-admin succeeds with no other role.
- `--owned`: another user's record → 404 on show/update/delete and absent from the list; a `.any` holder sees and changes it.
- `tenant-saas`: the generated tests set up permissions inside the tenant (extending `InteractsWithTenancy`).

### Docs, kit, quality gates

- Amend Phase 0 first: a new `docs/PERMISSIONS.md`, linked from `docs/PHASE-0.md`; update `ARCHITECTURE.md` (Policy row), `TENANCY.md` (spatie tables per database), `NAMING.md` (permission names).
- Update `README.md`, `rules.md`, `kit/skills/laravel-engineering`, `kit/skills/leb-generate-crud`, the Cursor rules, `stubs/README.md`, `CHANGELOG.md`, and Arabic strings in `lang/`.
- Package Pest tests for every refusal, sync behavior (expansion, typos, prune, idempotence), the generated output, tenant isolation and central/tenant separation.
- `bin/e2e`: each profile installs spatie, runs `engineering:install --permissions`, `make:crud ... --permissions --owned`, `permissions:sync`, then the app's tests.
- `composer check` stays green (Pint, PHPStan level 6, Pest).

## Left to the builder

- Exact console wording, file layout inside `src/Permissions/`, and stub names.
- How the definitions file is edited when adding a group (reuse the existing source-editing helpers).
- Whether to publish spatie's migration via its own publish tag or render a package stub.

## Also noticed (separate, small)

`docs/PHASE-0.md` ("Remaining: Phase 9, Phase 10") and `kit/skills/laravel-engineering/SKILL.md` ("Phases 0–8 shipped") are out of date compared with `README.md` (all phases done, 1.0.0). Fix alongside this work.

## Amendments (implementation)

Refinements made while implementing; the code follows these where they differ from the text above.

- **Owned single record, 404 first.** If the user lacks `<group>.view`, or is neither the owner nor a holder of `<group>.any`, the answer is 404 (not found). Only then does a missing action permission (`update`, `delete`) give 403. A record the user cannot view is always 404, so there is no existence probing; an owner without `.view` gets 404 on their own record.
- **`any()` policy method and Gate-based list scope.** The owned policy has a fifth ability `any()` (`<group>.any`). `List<Plural>` scopes to the user's records unless `$user->can('any', Model::class)` through the Gate, so the super-admin (via `Gate::before`) sees every record. `.any` only widens permissions the user holds; alone it grants nothing.
- **Update request stub.** The owned update request (`request-update-owned.stub`) authorizes with `Gate::inspect()`, so non-owners get 404, not 403.
- **`user_id`** is never fillable and never read from input; the create action associates the signed-in user.
- **`grantPermissions()` replaces roles.** `InteractsWithPermissions::grantPermissions($user, ...$names)` replaces all of the user's roles with one test role holding exactly those names; `makeSuperAdmin($user)` is additive.
- **Tenant cache isolation** does not depend on stancl's cache bootstrapper: the package sets the key `<cache key>.tenant.<id>` and resets spatie's in-memory permissions when tenancy starts and ends.
