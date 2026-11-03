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

## Customizing output

All stubs are rendered by the commands above. To customize them, run `php artisan vendor:publish --tag=blueprint-stubs` and edit `stubs/blueprint/*.stub` in the project; never hand-fill placeholders.

## Routes

- API: `Route::apiResource` with `only`/`except` as needed.
- `make:crud` adds `Route::apiResource(...)`; routes are protected by the generated policy (guests get 403). Add `auth:sanctum` in the module's `routes/api.php` for token APIs.

## After scaffold

Run pint/tests when the host project exists. Match `laravel-engineering` skill for review.

## Future

Web screens (Blade/Livewire/Vue) are not generated yet; `make:crud` is API-only.
