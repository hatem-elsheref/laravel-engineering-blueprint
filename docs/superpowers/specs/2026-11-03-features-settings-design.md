# Phase 7 — Features + Settings — Design Spec

**Date:** 2026-11-03
**Status:** Approved
**Builds on:** Phases 2 (profiles/install), 5 (`make:crud`), 6 (tenancy, `TenantSetup`, `tenant:create`).
**Supports:** PHP 8.3+, Laravel 12 and 13; `saas` and `tenant-saas` profiles.
**Authority:** `docs/FEATURES.md`, `docs/SETTINGS.md` (binding), `docs/TENANCY.md`, `docs/ARCHITECTURE.md` (`app/Shared/Features`, `app/Shared/Settings`).

---

## 1. Intent

Give `saas` and `tenant-saas` projects per-tenant feature flags and settings exactly as FEATURES.md and SETTINGS.md describe: definitions in code, state in each tenant's database, one central service each, backend enforcement, and platform-only changes. In `saas` the "tenant" is the whole app (one database); in `tenant-saas` it is the current stancl tenant.

## 2. Goals

1. Install (`saas`, `tenant-saas`) generates the definition classes and the two migrations.
2. `Feature` service + facade: read-only for app code; enforcement via route middleware, job middleware, `Feature::ensure()`, Blade `@feature`.
3. `Setting` service + facade: typed get with defaults, validated set, setting-level permissions.
4. Platform-admin console commands for features and settings, plus `features:sync` (the "controlled update process").
5. `tenant:create` steps 9–10 create defaults; test tenants get them too.
6. `make:crud --feature=<key>` protects generated routes.
7. Install summary: Features and Settings no longer "(coming in Phase 7)".

## 3. Non-goals

- Web admin panel or HTTP endpoints for changing features/settings. Features: no tenant write path at all. Settings: tenant code may write only `editable_by: tenant` settings, and only through their `ability` (§6).
- Feature-gated navigation/dashboard components beyond the Blade directive.
- Per-user features, percentages/rollouts, plans/billing.
- Caching beyond one request (FEATURES.md §4.4: "cache only when invalidation is reliable").
- Features/settings in `basic` and `modular` profiles.

## 4. Generated files (install, `saas`/`tenant-saas`)

All skipped with `already set up: <path>` if present.

- `app/Shared/Features/Features.php` (namespace from `blueprint.shared_path`, default `App\Shared\Features`):

```php
final class Features
{
    public const ORDERS = 'orders';
    public const REPORTS = 'reports';

    /**
     * Every feature key and its default state for new tenants.
     *
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return [
            self::ORDERS => true,
            self::REPORTS => false,
        ];
    }
}
```

- `app/Shared/Settings/Settings.php`:

```php
final class Settings
{
    public const APP_NAME = 'app.name';
    public const APP_LANGUAGE = 'app.language';
    public const ORDERS_AUTO_CONFIRM = 'orders.auto_confirm';

    /**
     * @return array<string, array{type: 'string'|'int'|'float'|'bool'|'array', default: mixed, editable_by: 'tenant'|'platform', ability?: string}>
     */
    public static function definitions(): array
    {
        return [
            self::APP_NAME => ['type' => 'string', 'default' => 'My App', 'editable_by' => 'tenant', 'ability' => 'manage-settings'],
            self::APP_LANGUAGE => ['type' => 'string', 'default' => 'en', 'editable_by' => 'tenant', 'ability' => 'manage-settings'],
            self::ORDERS_AUTO_CONFIRM => ['type' => 'bool', 'default' => false, 'editable_by' => 'platform'],
        ];
    }
}
```

- Migrations `create_features_table` (`id`, `key` string unique, `enabled` boolean, timestamps) and `create_settings_table` (`id`, `key` string unique, `value` json nullable, timestamps): in `database/migrations/tenant/` for `tenant-saas`, `database/migrations/` for `saas`.
- Config keys `blueprint.features` (default `App\Shared\Features\Features::class`) and `blueprint.settings` (default `App\Shared\Settings\Settings::class`).
- Summary `Next:` — `saas`: `php artisan migrate && php artisan features:sync`; `tenant-saas`: unchanged (`tenant:create` creates defaults).

## 5. Features

**Service** `Best\Blueprint\Features\FeatureManager` (singleton), facade `Best\Blueprint\Facades\Feature`:

- `enabled(string $key): bool` — unknown key (not in `defaults()`) → `UnknownFeature` exception (feature names can't be scattered). `tenant-saas` without an initialized tenant → `false` (fail safe). Missing row → `false`.
- `disabled(string $key): bool`.
- `ensure(string $key): void` — throws `FeatureDisabled` (HTTP 403 when rendered) if not enabled. For actions, jobs and scheduled commands.
- `all(): array<string, bool>` — every defined key with its current state (for tenant owners to *view*).
- No write methods. Writes go through `Best\Blueprint\Features\FeatureAdmin` (`enable`, `disable`, `sync`).
- *(Builder-check call)* **Runtime guard:** `FeatureAdmin` and `SettingAdmin` throw `AdminOnly` when called while handling an HTTP request (`! app()->runningInConsole()`), so no controller can become a tenant write path; console commands, queue workers and tests may call them. In `tenant-saas` they also throw when no tenant is initialized (never write to the central database).
- *(Builder-check call)* **Memo lifetime:** both managers are bound as `scoped`, which Laravel resets for every queued job (worker) and every Octane request; inside one request/job the memo is keyed by tenant id (`'app'` in `saas`) and dropped by admin writes and on `TenancyInitialized`/`TenancyEnded`. A long-running worker therefore never keeps a stale "enabled".

**Enforcement:**
- Route middleware alias `feature` (`->middleware('feature:orders')`): disabled → 403 (`abort(403, 'This feature is not enabled.')`); unknown key → 500 via `UnknownFeature` (a programming error).
- Job middleware `Best\Blueprint\Features\RequireFeature('orders')`: disabled → the job fails with `FeatureDisabled` (visible in failed jobs, never silently run).
- Blade: `@feature('orders') … @endfeature`.

## 6. Settings

**Service** `Best\Blueprint\Settings\SettingManager`, facade `Best\Blueprint\Facades\Setting`:

- `get(string $key): mixed` — unknown key → `UnknownSetting`. Stored value (JSON-decoded) converted to the definition's type; missing row → definition default. `tenant-saas` without a tenant → definition default.
- `set(string $key, mixed $value): void` — unknown key → `UnknownSetting`; value must match the type (`int` accepts integer-valued numeric strings, `bool` accepts `true/false/1/0/"1"/"0"`, `float` numeric, `array` array, `string` scalar) else `InvalidSettingValue`; `editable_by: platform` → `SettingNotEditable` (tenant code can never change it); *(builder-check call: deny by default)* `editable_by: tenant` **requires** an `ability` — none defined → `SettingNotEditable` — and `Gate::authorize($ability)` runs for the current user (no user, e.g. jobs → denied; trusted background code uses `SettingAdmin`). The generated abilities (`manage-settings`) are undefined until the app defines them, so they deny until then; the install summary says `Define the manage-settings Gate ability to let tenant owners change settings.` `tenant-saas` without a tenant → `RuntimeException('No tenant context.')`.
- `all(): array<string, mixed>`.
- Memo per request like features; cleared on `set`.
- Platform writes: `Best\Blueprint\Settings\SettingAdmin::set($key, $value)` — same type checks, ignores `editable_by`/`ability`. Console only.

## 7. Console commands (platform admin, central only)

`--tenant=<id>` is **required** in `tenant-saas` (runs inside that tenant via `tenancy()->find($id)->run(...)`; unknown id → `Tenant "<id>" not found.`) and **refused** in `saas` (`--tenant only applies to the tenant-saas profile.`). All refuse in other profiles (`Features need the saas or tenant-saas profile.`).

- `feature:list` — table key / enabled / default.
- `feature:enable {key}`, `feature:disable {key}` — unknown key refused; prints `Feature "<key>" enabled for tenant "<id>".` (`… for the app.` in `saas`).
- `features:sync {--tenant=*}` — adds a row (default state) for every defined feature key that has none; never changes existing rows; reports `added <n> feature(s) to <tenant>` per tenant (`to the app` in `saas`). Settings need no sync (defaults come from code). In `tenant-saas`, no `--tenant` → all tenants (the one exception to "`--tenant` is required").
- `setting:list`, `setting:set {key} {value}` — `value` parsed as JSON when valid JSON (`true`, `5`, `["a"]`, `"text"`), else used as a string; platform write via `SettingAdmin`.

## 8. Tenant creation

`TenantSetup` gains steps (inside the tenant):
- `Create default features` — insert a row per `Features::defaults()` key that is missing; `already` if none missing.
- `Create default settings` — nothing to insert (defaults live in code); reports `– Create default settings (already done: defaults come from Settings definitions)`. *(Kept as a step so the TENANCY.md list stays complete.)*

If the definition classes don't exist (feature tables not set up), both steps report `skipped: run engineering:install`.

## 9. `make:crud --feature=<key>`

- Key must be in `Features::defaults()` → else `Unknown feature "<key>". Add it to App\Shared\Features\Features first.`
- Route line gets `->middleware('feature:<key>')` via a `{{ middleware }}` placeholder in `route.stub`; if the rendered route doesn't contain `feature:<key>` (old published stub) → refuse (security: a requested gate must never be silently dropped).
- Generated tests enable the feature in `beforeEach` (`FeatureAdmin::enable`) and add a test that it answers 403 when disabled.

## 10. Testing (acceptance — green on Laravel 12 and 13)

- Install generates the files for `saas` (central migrations) and `tenant-saas` (tenant migrations); idempotent; nothing for `modular`.
- Feature: enabled/disabled/unknown/missing row/no tenant; memo cleared after admin change; isolation (tenant A enabled, B disabled); route middleware 403; job middleware fails the job; `ensure()`; Blade directive.
- Setting: typed get, defaults, set validation per type, platform-only refusal, tenant setting without ability refused, ability allowed/denied, no user denied, no tenant, isolation.
- Admin guard: `FeatureAdmin`/`SettingAdmin` throw during an HTTP request (route that calls them → 500 with `AdminOnly`), and in `tenant-saas` without a tenant; scoped reset: after `app()->forgetScopedInstances()` a changed state is seen.
- Commands: each happy path and refusal in `saas` and `tenant-saas`; `features:sync` adds only missing rows and keeps existing states.
- `tenant:create` steps 9–10; `make:crud --feature` route + tests + refusals; end-to-end: a generated tenant CRUD behind `feature:orders` answers 403 when disabled and works when enabled.

## 11. Left to the builder

- Exception class names/namespaces beyond those named; table rendering of list commands.
- How the Blade directive is registered (only when Blade is available).
