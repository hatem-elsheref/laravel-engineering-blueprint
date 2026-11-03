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

Run `php artisan engineering:install` first if the project has no profile. `module-provider.stub`, `module-routes-*.stub`, `dto.stub` and `action.stub` are rendered by these commands; their placeholders are filled by PHP and cannot be filled by hand.

Target module layout: `docs/ARCHITECTURE.md` (`Requests/`, `Controllers/`, `routes/`, `Provider.php`).

## Inputs to collect

- Module name (PascalCase): `Orders`
- Entity name (PascalCase): `Order`
- Options: `--api`, `--web`, `--soft-deletes`, `--parent=Order`, feature key if gated

## Remaining manual stubs (until `make:crud`)

Create under `app/Modules/{Module}/` by filling the stubs in `laravel-engineering-blueprint/stubs/`:

| Artifact | Stub |
|----------|------|
| Model | `model.stub` |
| Migration | `migration.stub` |
| Store/Update requests | `request.stub` (duplicate for Update*) |
| DTOs | `make:dto` (above) |
| Actions | `make:action` (above) — Create, Update, Delete, … |
| Controllers | `controller.stub` |
| Resource | `resource.stub` |
| Policy | `policy.stub` |
| Routes | `route.stub` |
| Feature test | `test.stub` |

## Placeholders

Replace in the manual stubs (model, migration, request, controller, resource, policy, route, test):

- `{{ module }}`, `{{ moduleNamespace }}`
- `{{ model }}`, `{{ modelVariable }}`, `{{ table }}`
- `{{ featureKey }}` (optional middleware comment)

## Routes

- API: `Route::apiResource` with `only`/`except` as needed.
- Apply `feature:` middleware or equivalent when `featureKey` is set.

## After scaffold

Run pint/tests when the host project exists. Match `laravel-engineering` skill for review.

## Future

`php artisan make:crud` (Phase 5) will render the remaining stubs — keep filenames stable.
