# Schema DSL (`make:crud --schema=`)

Use a YAML file instead of `--fields=` when you need enums, foreign keys, decimals with precision, soft deletes, or indexes.

```bash
php artisan make:crud Orders Order --schema=orders/order.yaml
```

Paths are resolved in order: absolute path, path relative to the app root, then under each directory in `config('blueprint.schema_paths')` (default: `schemas/`).

Do **not** combine `--schema` and `--fields`. When adding `--api` or `--web` to an existing CRUD, omit `--schema` (fields are read from the existing `*Data` class).

## File shape

```yaml
softDeletes: false   # optional
indexes:             # optional — column name or list of columns per index
  - status
  - [tenant_id, status]
unique:              # optional — same shape as indexes
  - sku
fields:              # required, non-empty
  - name: title
    type: string
    nullable: false
```

## Field types

| `type` | Notes |
|--------|--------|
| `string`, `text`, `int`, `float`, `bool`, `array` | Same as `--fields=`; optional `min`, `max`, `regex` (strings), `unique: true`, `default:`, `cast:` (`array` fields only: `array`, `collection`, `json`, `object`), `rules:` (list of validation rule class names) |
| `datetime`, `timestamp` | Stored as timestamp; PHP type `CarbonImmutable` |
| `decimal` | Optional `precision` (default 12), `scale` (default 2) |
| `foreignId` | Requires `references` (table). Optional `model` (`User` or `Billing.Invoice`), `onDelete` (`restrict`, `cascade`, …) |
| `enum` | Requires `cases` (strings). Optional `enum` class name (default `{Entity}{Field}` studly) |

Enums are generated under `app/Modules/{Module}/Enums/` and cast on the model. Validation uses `Rule::enum(...)`.

Automatic columns (`id`, `created_at`, `updated_at`, `deleted_at`) must not appear in `fields`.

## Example

See [schemas/examples/orders/order.yaml](../schemas/examples/orders/order.yaml).

## Entity options

| Key | Purpose |
|-----|---------|
| `softDeletes` | Adds `deleted_at` and `SoftDeletes` on the model |
| `indexes` / `unique` | Migration indexes (column or list of columns). Prefer field `unique: true` for a single column; entity `unique:` skips columns already marked on a field |
| `scopes` | Local scopes: list of `{ name, column, equals }` → `scope{Name}()` on the model |
| `relations` | Inverse relations (no nested routes): `hasMany` with `name`, `model`, optional `foreignKey` (default `{entity_snake}_id`). Related model must exist in the app (same as `foreignId`) |
| `filters` | List query filters: `{ column, query?, type }` — `equals`, `like`, `date_from`, `date_to`, `in`, `between` (optional `query_min`, `query_max`) |
| `id` | Optional `uuid` or `ulid` instead of bigint `id()` |
| `primaryKey` | `{ columns: [col, …] }` composite primary key (columns must appear in `fields`) |
| `resource` | `{ hidden: [column, …] }` — exclude from API Resource (field-level `hidden: true` also supported) |
| `profile` | Name of a file under `schemas/profiles/` (or `{schema_path}/profiles/`) whose `fields` are prepended |

Example `hasMany`:

```yaml
relations:
  - type: hasMany
    name: items
    model: OrderItem
    foreignKey: order_id
```

Example scopes:

```yaml
scopes:
  - name: paid
    column: status
    equals: paid
```

## Backlog

v2–v6 features are shipped (see [SCHEMA-BACKLOG.md](./SCHEMA-BACKLOG.md)). Nested API: [NESTED-RESOURCES.md](./NESTED-RESOURCES.md).
