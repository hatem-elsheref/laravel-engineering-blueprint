---
name: laravel-engineering
description: Use when building or reviewing Laravel modules, actions, DTOs, tenancy (stancl), tenant features/settings, or aligning code with the Laravel Engineering Blueprint Phase 0 specs.
---

# Laravel Engineering Blueprint

## Authority

Phase 0 constitution lives in the project bundle:

1. `docs/PHASE-0.md`
2. `docs/ARCHITECTURE.md`
3. `docs/NAMING.md`
4. `docs/TENANCY.md`
5. `docs/FEATURES.md`
6. `docs/SETTINGS.md`

Phase 0 is approved. The package `best/laravel-engineering-blueprint` implements it; package usage is in the bundle's `README.md`.

## Use the commands

Never hand-fill or copy stubs. Generate code with:

```bash
php artisan engineering:install --profile=modular   # basic | modular | saas | tenant-saas
php artisan make:module Orders --api --web
php artisan make:dto Orders OrderData --fields="name:string,quantity:int"
php artisan make:action Orders CancelOrder --data=OrderData --transaction
php artisan make:crud Orders Order --fields="name:string,quantity:int" --api --web
```

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

- Optional (`spatie/laravel-permission`): `php artisan engineering:install --permissions`; spec in `docs/PERMISSIONS.md`.
- Roles and permission names live in `app/Shared/Permissions/Permissions.php` (`CentralPermissions` for the tenant-saas central side); `php artisan permissions:sync` writes them. Never write permission names as strings elsewhere; never create permission rows by hand.
- `make:crud --permissions` generates a policy with named permissions (deny by default); add `--owned` for per-user records (`<uri>.any` widens). A record the user cannot view is 404.
- Grant the new group to a role in `roles()` and run `permissions:sync`; the super-admin role passes every check.
- Tests: `Best\Blueprint\Testing\InteractsWithPermissions` (`grantPermissions` replaces the user's roles; `makeSuperAdmin` adds).

## Status

All phases (0–10) are done; version 1.0.0 is prepared.

Full narrative: `rules.md` at kit root.

## Languages (interface translations)

- All user-facing text goes through `__()` with **English keys**: Blade `{{ __('Save') }}`, flash messages `__(':item created.', ['item' => __('Order')])`, API messages `abort(403, __('This feature is not enabled.'))`. Never hard-code display text in another language and never build sentences by concatenating strings.
- Translations live in `lang/<locale>.json` (e.g. `lang/ar.json`). The package ships Arabic for its own strings; `make:crud --web` adds the entity and field names for every language in `blueprint.locales` (value = English, to be translated).
- Configure the languages in `config/blueprint.php`: `'locales' => ['en', 'ar']`. The request language is picked automatically: API → `Accept-Language`; web → the visitor's choice (`route('blueprint.locale', 'ar')`); then the `app.language` setting; then the app default.
- Right-to-left (`ar`, `fa`, `he`, `ur`, see `blueprint.rtl_locales`) is handled by the layout (`<html lang dir>`). Use logical CSS (`margin-inline-start`, not `margin-left`) in custom pages.
- Laravel's own validation messages: `php artisan lang:publish` and add `lang/ar/validation.php` (or a translation package).
