# Phase 6 — Multi-tenancy (`stancl/tenancy`) — Design Spec

**Date:** 2026-11-03
**Status:** Draft for approval
**Builds on:** Phases 1–5 (profiles, install, `make:module`, `make:crud`, `Modules`, `FileWriter`, `StubRenderer`).
**Supports:** PHP 8.3+, Laravel 12 and 13, `stancl/tenancy` ^3.10 (3.10.1 supports Laravel 12 and 13; v4 is unreleased and needs PHP 8.4).
**Authority:** `docs/TENANCY.md` (binding), `docs/CONSTITUTION.md`, `docs/ARCHITECTURE.md`.

---

## 1. Intent

In the `tenant-saas` profile, a project gets working database-per-tenant multi-tenancy the way `docs/TENANCY.md` describes it — using `stancl/tenancy`'s own features, not a custom engine — plus a safe, retryable `tenant:create`, and generators that put tenant business code and data on the tenant side automatically. Other profiles are untouched.

## 2. Goals

1. **Setup** inside `engineering:install --profile=tenant-saas` (§4).
2. **`tenant:create`** implementing TENANCY.md §3.5 steps 1–11, safe to retry (§5).
3. **Route separation:** tenant module routes run only on tenant domains; central module routes run only on central domains (§6).
4. **Tenant-aware generators** in `tenant-saas`: `make:module` (tenant by default, `--central` opt-out) and `make:crud` (tenant migrations in `database/migrations/tenant`, tenant-aware generated tests) (§7).
5. **Isolation tests** in the package (§8).
6. Install summary: Tenancy no longer "(coming in Phase 6)".

## 3. Non-goals

- Running Composer for the user; installing `stancl/tenancy` v4.
- Custom tenant domains (only subdomains of a central domain now; TENANCY.md allows custom domains later without schema changes — domain records already store full hostnames).
- Features and Settings (Phase 7): `tenant:create` steps 9–10 report `skipped (coming in Phase 7)`.
- Changing the app's own `routes/web.php` / `routes/api.php` or `bootstrap/app.php` (see §6 note).
- Tenant deletion command, impersonation, tenant-aware cache/filesystem configuration beyond `stancl/tenancy` defaults.
- Migrating modules generated before switching to `tenant-saas`.

## 4. Setup (`engineering:install --profile=tenant-saas`)

Runs after the profile is recorded (Phase 2 flow). Each step is idempotent; re-running install skips what's done and reports `already set up: <thing>`.

1. **Package present?** `class_exists(Stancl\Tenancy\Tenancy::class)`. Missing → print `Tenancy needs stancl/tenancy. Run: composer require stancl/tenancy:^3.10` then `then re-run: php artisan engineering:install --profile=tenant-saas`; profile stays recorded; exit 0 with summary showing `✗ Tenancy (stancl/tenancy not installed)`.
2. **`tenancy:install`** (the package's command) if `config/tenancy.php` doesn't exist.
3. **Tenant model** `app/Models/Tenant.php` (from stub `tenant-model.stub`): `class Tenant extends Stancl\Tenancy\Database\Models\Tenant implements TenantWithDatabase` using `HasDatabase, HasDomains` — exactly the documented v3 shape. Skipped if the file exists.
4. **`config/tenancy.php` edits** — exact-string replacements on the published file; if an expected string isn't found (user edited it), print the manual instruction instead and continue:
   - `'tenant_model' => Tenant::class,` → `'tenant_model' => \App\Models\Tenant::class,`
   - the `central_domains` array → `['<APP_URL host>', 'localhost', '127.0.0.1']` (APP_URL host first; deduplicated; omitted if empty).
   - *(Builder-check call)* **Tenant base domain:** new package config key `blueprint.tenant_domain` (`env('BLUEPRINT_TENANT_DOMAIN')`, default `null`). Tenant domains are `<name>.<base>`, where base = `tenant_domain` if set, else the first non-IP central domain (so `APP_URL=https://example.com` → `acme.example.com`; local `localhost` → `acme.localhost`). Set it when the central app itself lives on a subdomain (`APP_URL=https://app.example.com`, `BLUEPRINT_TENANT_DOMAIN=example.com` → `acme.example.com`, as in TENANCY.md §3.4).
4a. *(Builder-check call)* **`routes/tenant.php`:** if `tenancy:install` just published it, replace its demo `/` route with an empty tenant group (same middleware, a comment, no routes) — the demo route otherwise shadows the app's `/` on central domains. An existing file is left alone.
4b. *(Builder-check call)* **Tenant users:** TENANCY.md §3.3 keeps tenant users in tenant databases. Copy the app's `database/migrations/*_create_users_table.php` into `database/migrations/tenant/` (same file name) if no `*_create_users_table.php` is there yet. Central keeps its own users table (platform admins). Report `copied users migration to database/migrations/tenant`.
5. **Provider registration:** `App\Providers\TenancyServiceProvider` added to `bootstrap/providers.php` via `ServiceProvider::addProviderToBootstrapFile`.
6. **Middleware groups and priority** are registered by the package itself (no app edit), only when stancl is installed and the profile is `tenant-saas`. *(Builder-check call)* The package also puts `PreventAccessFromCentralDomains` and `InitializeTenancyByDomain` at the top of the middleware priority (as stancl's provider stub does), so tenancy is identified before route-model binding and a central-domain request gets 404, not a 500 — without depending on the app's `TenancyServiceProvider`:
   - `tenant` → `[InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class]`
   - `central` → `[Best\Blueprint\Http\Middleware\CentralDomainsOnly::class]` — aborts 404 unless the request host is in `tenancy.central_domains`.
7. Summary: `✓ Tenancy` (no "coming in Phase 6"), plus `Next: php artisan migrate && php artisan tenant:create acme`.

## 5. `tenant:create`

Signature: `tenant:create {name : Tenant id, lowercase letters, numbers and dashes} {--domain= : Full domain, defaults to <name>.<first non-IP central domain>} {--database= : Database name, defaults to tenant_<name with - → _>}`

Gates (exit 1, nothing written): profile must be `tenant-saas`; stancl installed and set up (`config('tenancy.tenant_model')` class exists); run on the central context only (refuse if `tenancy()->initialized`).

Steps (TENANCY.md §3.5), each printed as `✓ <step>`, `– <step> (already done)` or `– <step> (skipped: <reason>)`:

1. **Validate name:** `^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$` (a valid subdomain label).
2. **Validate domain:** lowercase hostname; must end with `.<central domain>` for one of `tenancy.central_domains`; must not itself be a central domain. `--database` must match `^[A-Za-z0-9_]{1,64}$`.
3. **Duplicates:** domain already belongs to a **different** tenant → refuse `Domain "<d>" already belongs to tenant "<id>".` Tenant `<name>` exists with a **different** database name or domain → refuse `Tenant "<name>" already exists with database "<db>" and domain "<d>".` Exists with the **same** values → resume (retry).
4. **Tenant record:** create with `id` = name and `tenancy_db_name` = database, **without model events** (so the app's own `TenantCreated` pipeline doesn't run half the steps; stancl's VirtualColumn still stores `tenancy_db_name`); `already done` if it exists.
5. **Domain record:** create if missing — **with** model events (stancl's domain checks run as `saving` listeners).
6. **Database:** create via the tenant's database manager unless it already exists (`databaseExists`).
7. **Tenant migrations:** `tenants:migrate --tenants=<id>` (idempotent).
8. **Seed:** `tenants:seed --tenants=<id> --class=Database\Seeders\TenantSeeder` if that class exists; else `skipped: no Database\Seeders\TenantSeeder`.
9. **Default features:** `skipped: coming in Phase 7`.
10. **Default settings:** `skipped: coming in Phase 7`.
11. **Report:** `Tenant "<id>" is ready at http://<domain>` (scheme from APP_URL). Exit 0.

If any step 4–8 throws: end tenancy if it is active *(builder-check call: stancl's `runForMultiple` leaves it on after a failure)*, print `✗ <step>: <message>`, then `Fix the problem and run the same command again; finished steps are skipped.`; exit 1. No rollback (records kept so a retry resumes) — this is the "safe to retry" rule.

`tenant:create` is a central console command only; no HTTP endpoint (TENANCY.md §3.5).

## 6. Route separation

*(Builder-check call — Laravel matches routes by method + domain + URI in registration order, not by middleware, so middleware alone can't separate routes that share a URI. This follows stancl's documented v3 pattern.)*

- **Central modules** (`make:module X --central`): Provider registers routes **inside `Route::domain($domain)` for each `tenancy.central_domains` entry**, during `boot()`, with `['api', 'central']` / `['web', 'central']` (the `central` group stays as defense in depth). Domain-scoped routes only match central hosts.
- **Tenant modules** (default in `tenant-saas`): Provider registers routes in `$this->app->booted(...)` — after all central routes — with `Route::middleware(['api', 'tenant'])->prefix('api')` / `['web', 'tenant']`. Because central routes are domain-scoped and registered first, a shared URI resolves to the central route on central hosts and to the tenant route on tenant hosts.
- Modules in other profiles: unchanged (Phase 3 shape).
- `CentralDomainsOnly` middleware: 404 when `$request->getHost()` is not in `tenancy.central_domains`.
- **Note printed by setup** (not automated): `Your app's routes/web.php and routes/api.php answer on every domain and win over tenant routes with the same URI. Wrap central-only routes in Route::domain(...) for each central domain (see stancl/tenancy docs) or move them into a --central module.`

## 7. Tenant-aware generators (`tenant-saas` only)

- **`make:module`** gets `--central`. Default (tenant) Provider and route stubs as §6. Generated Provider includes the group name, which is how other commands tell tenant from central modules: a module is **central** iff its `Provider.php` contains `'central'`; otherwise tenant. `--central` outside `tenant-saas` → refuse `--central only applies to the tenant-saas profile.`
- **`make:crud`** in a tenant module:
  - migration path `database/migrations/tenant/<ts>_create_<table>_table.php` (collision check looks there too);
  - generated test uses the package's testing helper (§7.1) and requests the tenant domain;
  - everything else identical to Phase 5.
  - In a central module: Phase 5 behavior (central migration path) and requests go to the first central domain.
- **`make:dto`/`make:action`**: unchanged (no data location).

### 7.1 Testing helper (shipped by the package)

`Best\Blueprint\Testing\InteractsWithTenancy` (trait for Pest/PHPUnit test cases):
- `createTestTenant(string $id = 'test'): Tenant` — creates tenant + domain `<id>.<first central domain>` + database + tenant migrations (same steps as `tenant:create` 4–7, via one shared service class `Best\Blueprint\Tenancy\TenantSetup`), initializes tenancy, returns the tenant.
- `tenantUrl(string $path): string` — `http://<tenant domain><path>`.
- Cleanup after each test: end tenancy, delete the tenant's database and records.

Generated tenant test: `uses(InteractsWithTenancy::class, DatabaseMigrations::class)` *(builder-check call: not `RefreshDatabase` — `CREATE DATABASE` inside its transaction commits it on MySQL/PostgreSQL and leaks rows between tests)*; `beforeEach` → `$this->tenant = $this->createTestTenant();` then `User::factory()->create()` (lands in the tenant database because tenancy is initialized; needs the tenant users migration from §4b); requests use `$this->tenantUrl('/api/<uri>')`. Same scenarios as Phase 5. Supported drivers for tenant databases: whatever stancl's managers support (SQLite, MySQL, PostgreSQL); the package's own tests run on SQLite.

## 8. Package tests (acceptance — green on Laravel 12 and 13)

Testbench with sqlite (central in a temp file; tenant DBs via stancl's SQLite manager).

1. Setup: missing-stancl path (simulated), full setup creates/edits exactly the files in §4, re-run reports `already set up`; edited `config/tenancy.php` → manual instruction, no crash.
2. `tenant:create`: happy path (all 11 lines); each validation refusal; duplicate domain; existing tenant with different values; **retry**: fail at step 7 (bad tenant migration), fix, re-run → steps 4–6 `already done`, 7–8 run; second identical run → all `already done`, exit 0.
3. Isolation: two tenants, a row written in A's context isn't visible in B's; tenant route on central domain → 404; central-module route on tenant domain → 404; **same URI** in a central and a tenant module → each host gets its own route; **queued job** *(builder-check call: sync proves nothing)* — `database` queue driver, dispatch in tenant A, end tenancy, run `queue:work --once` from the central context → the job writes to A's database, not central or B; **scheduled tasks** — `tenants:run <command>` runs the command once per tenant against each tenant's database (the documented v3 way to schedule tenant work: `Schedule::command('tenants:run …')`).
4. Generators: tenant/central `make:module` providers; `--central` refused outside `tenant-saas`; `make:crud` in tenant module writes to `database/migrations/tenant`; end-to-end: generated tenant CRUD answers on the tenant domain (guest 403, CRUD works) and 404 on the central domain.
5. All Phase 1–5 tests unchanged and green.

**Real-app check (final review):** fresh Laravel 13 app + Pest + `stancl/tenancy` → install `tenant-saas` → `migrate` → `tenant:create acme` → `make:module Orders` → `make:crud Orders Order …` → `tenants:migrate` → `php artisan test` passes; `curl` against `acme.localhost` vs `localhost` shows the separation.

## 9. Package dependencies

`stancl/tenancy` goes in `require-dev` (for tests) and `suggest` (for apps); the package must not fatal when it's absent — every tenancy class reference is behind `class_exists`.

## 10. Left to the builder

- Stub names and placeholders for the tenant model, tenant/central provider variants and tenant test.
- How the "simulated missing stancl" test is done (e.g. an injectable detector like `PestDetector`).
- Exact wording of step lines beyond what's fixed above.
