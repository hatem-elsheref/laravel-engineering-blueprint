---
name: laravel-engineering
description: Use when building or reviewing Laravel modules, actions, DTOs, tenancy (stancl), tenant features/settings, or aligning code with the Laravel Engineering Blueprint Phase 0 specs.
---

# Laravel Engineering Blueprint

## Authority

Follow `docs/DOCUMENT-HIERARCHY.md`. Core specs:

1. `docs/PHASE-0.md`, `docs/ARCHITECTURE.md`, `docs/MODULE-BOUNDARIES.md`
2. `docs/NAMING.md`
3. `docs/TENANCY.md`, `docs/TENANCY-EXTENSIBILITY.md`
4. `docs/FEATURES.md`, `docs/SETTINGS.md`, `docs/PERMISSIONS.md`
5. `docs/SECURITY-GENERATION.md`, `docs/AI-WORKFLOW.md`, `docs/ROADMAP.md`

Phase 0 is approved. Package usage: bundle `README.md`.

## Use the commands

After module changes run `php artisan engineering:architecture` and PHPStan with `vendor/best/laravel-engineering-blueprint/phpstan/blueprint-module-boundaries.neon` (catches FQCN/static references without `use`).

Never hand-fill or copy stubs. Generate code with:

```bash
php artisan engineering:install --profile=modular   # basic | modular | saas | tenant-saas
php artisan make:module Orders --api --web
php artisan make:dto Orders OrderData --fields="name:string,quantity:int"
php artisan make:action Orders CancelOrder --data=OrderData --transaction
php artisan make:crud Orders Order --fields="name:string,quantity:int" --api --web --permissions
```

Register auth middleware on the module `Provider.php`. Default `make:crud` policy is **deny-by-default**; use `--permissions` in production or `--open-policy` for demos only.

For CRUD details use skill **leb-generate-crud**. To change generated code, run `php artisan vendor:publish --tag=blueprint-stubs` and edit `stubs/blueprint/*.stub`.

## Architecture defaults (from ARCHITECTURE.md)

- **Modules:** `app/Modules/{Name}/` with only needed folders (`Actions`, `Data`, `Requests`, `Controllers`, `routes/`, `Provider.php`, …).
- **Shared:** `app/Shared/Features`, `Settings`, `Support`, `Contracts`.
- **Controller** — request/response only.
- **Request** — validation + authorization.
- **Data** — typed transfer (native PHP DTOs).
- **Action / Query** — write vs read operations.
- **Flow:** Route → Middleware → Request → Data → Controller → Action|Query → Model|Service → Resource|View (use only needed layers).

## Naming

Follow `docs/NAMING.md`. Folder implies type (`Actions/CreateOrder.php`).

## Tenancy & features & settings

- `docs/TENANCY.md` — central vs tenant DB, `tenant:create`, `make:module --central`, migration paths (`tenants:migrate`), isolation, `CACHE_STORE=redis`.
- `docs/FEATURES.md` — `features` table (tenant DB in tenant-saas, app DB in saas), `Feature::enabled()`, `feature:` middleware, `RequireFeature` job middleware, `@feature`; writes only via `feature:*` / `features:sync` commands.
- `docs/SETTINGS.md` — `settings` table, typed `Setting::get`, `Setting::set` for tenant-editable settings (Gate ability), `setting:set` for platform settings.

## Permissions

- Optional (`spatie/laravel-permission` 8+; teams mode is refused): `php artisan engineering:install --permissions`, then `php artisan migrate`; spec in `docs/PERMISSIONS.md`. `blueprint.permission_guard` must be the User model's guard.
- tenant-saas app with existing tenants: `php artisan migrate && php artisan tenants:migrate && php artisan permissions:sync && php artisan permissions:sync --central`. `permissions:sync` reports a tenant without the tables ("no permission tables — run php artisan tenants:migrate") and exits 1.
- Roles and permission names live in `app/Shared/Permissions/Permissions.php` (`CentralPermissions` for the tenant-saas central side); `php artisan permissions:sync` writes them. Never write permission names as strings elsewhere; never create permission rows by hand.
- `make:crud --permissions` generates a policy with named permissions (deny by default); add `--owned` for per-user records (`<uri>.any` widens). A record the user cannot view is 404.
- Grant the new group to a role in `roles()` and run `permissions:sync`; the super-admin role passes every check.
- Tests: `Best\Blueprint\Testing\InteractsWithPermissions` (`grantPermissions` replaces the user's roles; `makeSuperAdmin` adds).

## Status

Phase 0 architecture is approved and the 1.0 toolkit is shipped; runtime extras (API envelope, idempotency middleware) remain in `docs/STANDARDS-BACKLOG.md`. See `docs/ENGINEERING-AUDIT.md` for gaps.

Full narrative: repository root `rules.md` (specs in `docs/` win on conflict — `docs/DOCUMENT-HIERARCHY.md`).

## Languages (interface translations)

- All user-facing text goes through `__()` with **English keys**: Blade `{{ __('Save') }}`, flash messages `__(':item created.', ['item' => __('Order')])`, API messages `abort(403, __('This feature is not enabled.'))`. Never hard-code display text in another language and never build sentences by concatenating strings.
- Translations live in `lang/<locale>.json` (e.g. `lang/ar.json`). The package ships Arabic for its own strings; `make:crud --web` adds the entity and field names for every language in `blueprint.locales` (value = English, to be translated).
- Configure the languages in `config/blueprint.php`: `'locales' => ['en', 'ar']`. The request language is picked automatically: API → `Accept-Language`; web → the visitor's choice (`route('blueprint.locale', 'ar')`); then the `app.language` setting; then the app default.
- Right-to-left (`ar`, `fa`, `he`, `ur`, see `blueprint.rtl_locales`) is handled by the layout (`<html lang dir>`). Use logical CSS (`margin-inline-start`, not `margin-left`) in custom pages.
- Laravel's own validation messages: `php artisan lang:publish` and add `lang/ar/validation.php` (or a translation package).
