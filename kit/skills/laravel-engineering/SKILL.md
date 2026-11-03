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

## Status

Phases 0–8 are shipped (specs, package, generators, tenancy, features, settings, Blade pages). Remaining: Phase 9 (starter app), Phase 10 (release).

Full narrative: `rules.md` at kit root.
