---
name: leb-generate-crud
description: Use when scaffolding a new Laravel module, Data class, Action or CRUD (JSON API or Blade pages) inside the Blueprint.
---

# Generate modules and CRUD

Phase 0 is approved and the package `best/laravel-engineering-blueprint` does the generating. Always run the commands; never hand-fill or copy stubs.

## Commands

| Artifact | Command |
|----------|---------|
| Module (`Provider.php`, optional routes) | `php artisan make:module Orders --api --web` |
| Platform module (tenant-saas, central domains only) | `php artisan make:module Plans --central --api` |
| Data class | `php artisan make:dto Orders OrderData --fields="name:string,quantity:int,note:?string"` |
| Action | `php artisan make:action Orders CreateOrder --data=OrderData --transaction` |
| CRUD, JSON API (default) | `php artisan make:crud Orders Order --fields="name:string,quantity:int,note:?string"` |
| CRUD, Blade pages | `php artisan make:crud Orders Order --fields="name:string,quantity:int" --web` |
| CRUD, both sides | `php artisan make:crud Orders Order --fields="name:string,quantity:int" --api --web` |
| CRUD behind a feature (saas, tenant-saas) | `php artisan make:crud Orders Order --fields="..." --feature=orders` |

Run `php artisan engineering:install --profile=modular` (or `saas`, `tenant-saas`) first if the project has no profile. Create the module with `make:module` before `make:crud`.

## What make:crud writes

| Side | Files |
|------|-------|
| Shared | Model, migration, `database/factories/<Module>/<Entity>Factory.php`, `Store<Entity>Request`, `Update<Entity>Request`, `<Entity>Data`, `Create/Update/Delete<Entity>` actions, `List<Plural>` query, `<Entity>Policy` |
| `--api` | `<Entity>Controller`, `<Entity>Resource`, route in `routes/api.php`, `tests/Feature/Modules/<Module>/<Entity>ApiTest.php` |
| `--web` | `<Entity>PageController`, views `resources/views/<module-kebab>/<entity-plural-kebab>/{index,create,edit,show,_form}.blade.php`, route in `routes/web.php`, `tests/Feature/Modules/<Module>/<Entity>WebTest.php` |

- Neither flag = `--api`. Add the other side later with the same command and the new flag; it reuses the existing fields and feature gate.
- Pages are plain Blade (no JS) and `@extends(config('blueprint.layout'))`; `resources/views/layouts/blueprint.blade.php` is created once.
- Tests are Pest; skipped with a message if Pest is missing. Web tests fail with 419 when config is cached: run `php artisan optimize:clear` before tests.

## Inputs to collect

- Module name (PascalCase): `Orders`
- Entity name (PascalCase, singular; for `--web` not uncountable like `Equipment`): `Order`
- Fields: `--fields="name:string,quantity:int,price:?float,active:bool,tags:array,shippedAt:?Carbon\CarbonImmutable"` (types: string, int, float, bool, array, Carbon\CarbonImmutable; `?` = nullable)

## Routes

| Route | Name |
|-------|------|
| API | `api.<uri>.*` (e.g. `api.orders.index`) |
| Web | `<uri>.*` (e.g. `orders.index`) |
| Central module | `central.api.<uri>.*`, `central.<uri>.*` |

- `make:crud` adds `Route::apiResource(...)` (API) or `Route::resource(...)` (web) to the module's route file.
- The generated policy allows every signed-in user and denies guests. Tighten it. Guests get 403 also for missing ids.
- Auth middleware for token APIs (e.g. `auth:sanctum`): add it to the API route group in the module's `Provider.php`.

## tenant-saas profile (multi-tenancy)

Requires `composer require stancl/tenancy:^3.10`, then `php artisan engineering:install --profile=tenant-saas` and `php artisan migrate`.

- Create tenants only with `php artisan tenant:create acme` (domain `acme.<base>`, database `tenant_acme`; base from `BLUEPRINT_TENANT_DOMAIN`); safe to re-run after a failure.
- `make:module Orders` makes a **tenant** module (`tenant` middleware group, tenant domains only); `make:module Admin --central` makes a **platform** module (`central` group, central domains only).
- `make:crud` in a tenant module writes its migration to `database/migrations/tenant/`; run it with `php artisan tenants:migrate`. Never put tenant tables in `database/migrations/`.
- Generated tenant tests use `Best\Blueprint\Testing\InteractsWithTenancy` (`createTestTenant()`, `tenantUrl()`; a real test tenant per test).
- Schedule tenant work with `tenants:run <command>`. The package keeps the database queue on the central connection.
- Use `CACHE_STORE=redis` (or memcached): tenant cache isolation needs tags; `database`/`file` stores fail inside tenants.
- Web sessions are tied to the tenant (or central app) where they started.

## Features and settings (saas, tenant-saas)

- Keys live only in `app/Shared/Features/Features.php` (`defaults()`) and `app/Shared/Settings/Settings.php` (`definitions()`); never use string keys that aren't defined there.
- Check features with `Feature::enabled(Features::ORDERS)` / `Feature::ensure(...)`; protect routes with `->middleware('feature:orders')` (or `make:crud ... --feature=orders`), jobs with `new RequireFeature(Features::ORDERS)` in `middleware()`, views with `@feature('orders')`. Hiding a button is not security.
- `feature:a,b` requires every listed feature. In saas tests the features table starts empty (all off): call `app(FeatureAdmin::class)->sync()` or `->enable(...)` in `beforeEach`.
- Never write feature states from app code. Platform admins use `php artisan feature:enable|disable <key>`, `feature:list`, `features:sync` (after adding a new key).
- `--tenant=<id>` is required in tenant-saas and refused in saas. `features:sync` without `--tenant` syncs all tenants.
- `Setting::get('app.language')` is typed with defaults. `Setting::set()` only works for `editable_by: tenant` settings, through their Gate `ability` (define `manage-settings`). Platform settings: `php artisan setting:set <key> <json> [--tenant=<id>]`.

## Customizing output

To change generated code, run `php artisan vendor:publish --tag=blueprint-stubs` and edit `stubs/blueprint/*.stub` (or set `blueprint.stub_path`). Stubs with leftover placeholders, missing classes, a missing `->names('api.<uri>')` in `route.stub`, or that would drop a requested feature gate are refused. `laravel-engineering-blueprint/stubs/` is a reference copy only.

## After scaffold

Tighten the policy, run migrations (`migrate` / `tenants:migrate`), then Pint and the tests. Match the **laravel-engineering** skill for review.

## Languages (interface translations)

- All user-facing text goes through `__()` with **English keys**: Blade `{{ __('Save') }}`, flash messages `__(':item created.', ['item' => __('Order')])`, API messages `abort(403, __('This feature is not enabled.'))`. Never hard-code display text in another language and never build sentences by concatenating strings.
- Translations live in `lang/<locale>.json` (e.g. `lang/ar.json`). The package ships Arabic for its own strings; `make:crud --web` adds the entity and field names for every language in `blueprint.locales` (value = English, to be translated).
- Configure the languages in `config/blueprint.php`: `'locales' => ['en', 'ar']`. The request language is picked automatically: API → `Accept-Language`; web → the visitor's choice (`route('blueprint.locale', 'ar')`); then the `app.language` setting; then the app default.
- Right-to-left (`ar`, `fa`, `he`, `ur`, see `blueprint.rtl_locales`) is handled by the layout (`<html lang dir>`). Use logical CSS (`margin-inline-start`, not `margin-left`) in custom pages.
- Laravel's own validation messages: `php artisan lang:publish` and add `lang/ar/validation.php` (or a translation package).
