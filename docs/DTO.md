# DTO (Data) standard

LEB uses **native PHP** typed classes for structured data — no serialization framework.

## Naming and location

- Module path: `app/Modules/{Module}/Data/{Name}.php`
- Generated CRUD input object: `{Entity}Data` from `make:crud` (`crud-data.stub`).
- Manual objects: `php artisan make:dto {Module} {Name}` (`dto.stub`).

See [NAMING.md](./NAMING.md).

## Generated CRUD Data class

- **Constructor:** public typed properties matching entity fields.
- **`fromRequest(Store|Update*Request $request)`:** maps validated input to properties (nullable handling per field kind).
- **`toArray()`:** column keys for persistence layer in Actions.

Actions accept `{Entity}Data` — not `Request` instances.

## Principles

1. **`declare(strict_types=1);`** on all Data classes.
2. **Immutability:** prefer `readonly` classes or readonly properties when the object represents a snapshot (manual DTOs).
3. **Explicit construction:** constructor or named factories (`fromRequest`, `fromArray`) — avoid magic setters.
4. **Enums:** use backed enums on properties when the schema or model uses enums.
5. **Dates:** `CarbonImmutable` (or project standard) with timezone awareness at the boundary.
6. **When to skip a DTO:** trivial internal helpers where a scalar or array is clearer — do not force Data on every method.

## Input vs output

- **Input Data** (CRUD): built from Form Request after validation.
- **Output:** prefer API **Resources** for HTTP JSON; use output DTOs only when the same shape is reused outside HTTP (exports, jobs).

## Testing

- Unit-test `fromRequest` mapping when normalization is non-trivial.
- Feature tests should drive HTTP and assert persistence/API — not every Data line.
