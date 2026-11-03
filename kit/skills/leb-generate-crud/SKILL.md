---
name: leb-generate-crud
description: Use when scaffolding a new Laravel module or CRUD inside the Blueprint (manual stub phase before Artisan generator exists).
---

# Generate CRUD (stub phase)

**Gate:** Use only after Phase 0 is approved and Phase 1+ Laravel foundation exists. During Phase 0, do not scaffold application code.

Artisan commands are **not** wired yet. Use `kit/stubs/` and transform placeholders manually (or with agent edits).

Target module layout: `docs/ARCHITECTURE.md` (`Requests/`, `Controllers/`, `routes/`, `Provider.php`). Stubs will be realigned in Phase 3 to match namespaces.

## Inputs to collect

- Module name (PascalCase): `Orders`
- Entity name (PascalCase): `Order`
- Options: `--api`, `--web`, `--soft-deletes`, `--parent=Order`, feature key if gated

## File checklist

Create under `app/Modules/{Module}/`:

| Artifact | Stub |
|----------|------|
| Model | `model.stub` |
| Migration | `migration.stub` |
| Store/Update requests | `request.stub` (duplicate for Update*) |
| DTOs | `dto.stub` |
| Actions | `action.stub` (Create, Update, Delete, …) |
| Controllers | `controller.stub` |
| Resource | `resource.stub` |
| Policy | `policy.stub` |
| Routes | `route.stub` |
| Feature test | `test.stub` |

## Placeholders

Replace in all stubs:

- `{{ module }}`, `{{ moduleNamespace }}`
- `{{ model }}`, `{{ modelVariable }}`, `{{ table }}`
- `{{ featureKey }}` (optional middleware comment)

## Routes

- API: `Route::apiResource` with `only`/`except` as needed.
- Apply `feature:` middleware or equivalent when `featureKey` is set.

## After scaffold

Run pint/tests when the host project exists. Match `laravel-engineering` skill for review.

## Future

`php artisan make:module` / `make:crud` will render these stubs — keep filenames stable.
