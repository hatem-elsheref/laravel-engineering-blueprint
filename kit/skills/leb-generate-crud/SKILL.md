---
name: leb-generate-crud
description: Use when scaffolding a new Laravel module, Data class, Action or CRUD inside the Blueprint.
---

# Generate modules and CRUD

**Gate:** Use only after Phase 0 is approved and Phase 1+ Laravel foundation exists. During Phase 0, do not scaffold application code.

## Use the generators first

When the `best/laravel-engineering-blueprint` package is installed, never hand-fill these stubs — run the command:

| Artifact | Command |
|----------|---------|
| Module (`Provider.php`, optional routes) | `php artisan make:module Orders --api --web` |
| Data class | `php artisan make:dto Orders OrderData --fields="name:string,quantity:int,note:?string"` |
| Action | `php artisan make:action Orders CreateOrder --data=OrderData --transaction` |
| Full API CRUD (model, migration, factory, requests, data, actions, query, controller, resource, policy, route, Pest tests) | `php artisan make:module Orders --api` then `php artisan make:crud Orders Order --fields="name:string,quantity:int,note:?string"` |

Run `php artisan engineering:install` first if the project has no profile. `module-provider.stub`, `module-routes-*.stub`, `dto.stub` and `action.stub` are rendered by these commands; their placeholders are filled by PHP and cannot be filled by hand.

Target module layout: `docs/ARCHITECTURE.md` (`Requests/`, `Controllers/`, `routes/`, `Provider.php`).

## Inputs to collect

- Module name (PascalCase): `Orders`
- Entity name (PascalCase): `Order`
- Entity must be singular. Fields: `--fields="name:string,quantity:int,price:?float,active:bool,tags:array,shippedAt:?Carbon\CarbonImmutable"` (types: string, int, float, bool, array, Carbon\CarbonImmutable; `?` = nullable)

## tenant-saas profile (multi-tenancy)

Requires `composer require stancl/tenancy:^3.10`, then `php artisan engineering:install --profile=tenant-saas` and `php artisan migrate`.

- Create tenants only with `php artisan tenant:create acme` (domain `acme.<base>`, database `tenant_acme`); safe to re-run after a failure.
- `make:module Orders` makes a **tenant** module (routes answer only on tenant domains); `make:module Admin --central` makes a **platform** module (routes answer only on central domains).
- `make:crud` in a tenant module writes its migration to `database/migrations/tenant/`; run it with `php artisan tenants:migrate`. Never put tenant tables in `database/migrations/`.
- Generated tenant tests use `Best\Blueprint\Testing\InteractsWithTenancy` (real test tenant per test).
- Schedule tenant work with `tenants:run <command>`. The package keeps the database queue on the central connection.
- Use `CACHE_STORE=redis` (or memcached): tenant cache isolation needs tags; `database`/`file` stores fail inside tenants.
- Central module routes are named `central.*` (e.g. `route('central.plans.index')`).

## Features and settings (saas, tenant-saas)

- Keys live only in `app/Shared/Features/Features.php` (`defaults()`) and `app/Shared/Settings/Settings.php` (`definitions()`); never use string keys that aren't defined there.
- Check features with `Feature::enabled(Features::ORDERS)` / `Feature::ensure(...)`; protect routes with `->middleware('feature:orders')` (or `make:crud ... --feature=orders`), jobs with `new RequireFeature(Features::ORDERS)` in `middleware()`, views with `@feature('orders')`. Hiding a button is not security.
- `feature:a,b` requires every listed feature. In saas tests the features table starts empty (all off): call `app(FeatureAdmin::class)->sync()` or `->enable(...)` in `beforeEach`.
- Never write feature states from app code. Platform admins use `php artisan feature:enable|disable <key> --tenant=<id>`, `feature:list`, `features:sync` (after adding a new key).
- `Setting::get('app.language')` is typed with defaults. `Setting::set()` only works for `editable_by: tenant` settings, through their Gate `ability` (define `manage-settings`). Platform settings: `php artisan setting:set <key> <json> --tenant=<id>`.

## Customizing output

All stubs are rendered by the commands above. To customize them, run `php artisan vendor:publish --tag=blueprint-stubs` and edit `stubs/blueprint/*.stub` in the project; never hand-fill placeholders.

## Routes

- API: `Route::apiResource` with `only`/`except` as needed.
- `make:crud` adds `Route::apiResource(...)`; routes are protected by the generated policy (guests get 403). Add `auth:sanctum` in the module's `routes/api.php` for token APIs.

## After scaffold

Run pint/tests when the host project exists. Match `laravel-engineering` skill for review.

## Future

Web screens (Blade/Livewire/Vue) are not generated yet; `make:crud` is API-only.
