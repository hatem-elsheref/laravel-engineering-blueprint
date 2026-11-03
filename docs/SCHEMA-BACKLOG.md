# Schema DSL backlog

Authority for **what ships next** in `make:crud --schema=`. v1 is documented in [SCHEMA-DSL.md](./SCHEMA-DSL.md).

## Shipped (v1)

| Item | Schema | Generated |
|------|--------|-----------|
| Core field types | `fields[].type` | Migration, Data, validation, factory, forms |
| Nullable | `nullable: true` | Column + rules |
| Decimal precision | `precision`, `scale` | Migration decimal |
| Enums | `type: enum`, `cases`, `enum` | PHP enum file, cast, `Rule::enum` |
| Foreign keys | `type: foreignId`, `references`, `model`, `onDelete` | Migration FK, `belongsTo` |
| Soft deletes | `softDeletes: true` | Migration + `SoftDeletes` trait |
| Indexes / unique | `indexes`, `unique` | Migration index/unique |
| Path resolution | `blueprint.schema_paths` | Loader |

## Shipped (v2)

| Item | Schema | Generated |
|------|--------|-----------|
| Local scopes | `scopes[]` with `name`, `column`, `equals` | Model `scope{Name}` methods |
| Field validation | `min`, `max`, `regex` on supported types | Store/update FormRequest rules |
| Field unique | `unique: true` on a field | Column `->unique()`, store `unique:table,column`, update `Rule::unique(...)->ignore(route)` |

## Shipped (v3)

| Item | Schema | Generated |
|------|--------|-----------|
| Custom casts | `cast: array\|collection\|json\|object` on `array` fields | Model `casts()` |
| Default values | `default:` on supported field types | Migration `->default(...)`, factory literal |
| hasMany relations | `relations[]` with `type: hasMany`, `name`, `model`, optional `foreignKey` | Model `hasMany()` (no nested routes) |

## Shipped (v4)

| Item | Schema | Generated |
|------|--------|-----------|
| Query filters | `filters[]` with `column`, `query`, `type` (`equals`, `like`, `date_from`, `date_to`) | `List*` query + `Request` on index (not with `--owned`) |
| Resource shaping | Field `hidden: true` or `resource.hidden[]` | Omits columns from API Resource |
| Schema profiles | `profile: catalog` | Merges `schemas/profiles/{name}.yaml` fields before entity fields |

`hasMany` still assumes the related model exists (same as `foreignId`).

## Shipped (v5)

| Item | Schema | Generated |
|------|--------|-----------|
| UUID / ULID id | `id: uuid` or `id: ulid` | Migration PK, `HasUuids` / `HasUlids`, list `latest('created_at')` |
| Composite PK | `primaryKey.columns: [col, …]` (≥2, all in `fields`) | Migration `$table->primary([…])`, `$incrementing = false` (route binding: first column) |
| Filter `in` | `type: in` | `whereIn` from request array |
| Filter ranges | `type: between`, optional `query_min` / `query_max` | Numeric/date `>=` / `<=` filters |
| Custom rules | field `rules: [FQCN, …]` | Appended in FormRequest `rules()` as `new Class()` |
| Profile | `transactional` | `schemas/profiles/transactional.yaml` (uuid id, amount, currency, occurred_at) |

## Shipped (v6 — nested API)

| Item | CLI | Generated |
|------|-----|-----------|
| Nested child CRUD | `make:nested-crud` / `make:crud --nested-under=Parent` | Nested `apiResource`, parent FK, scoped list query — [NESTED-RESOURCES.md](./NESTED-RESOURCES.md) |

## Next (prioritized)

| Priority | Item | Notes |
|----------|------|--------|
| — | (empty) | File new items here before implementation |

## Explicitly out of scope (for now)

- Replacing `--fields=` for simple CRUD (both stay supported).
- Auto auth middleware on routes (see [SECURITY-GENERATION.md](./SECURITY-GENERATION.md)).
- Full OpenAPI from schema (see [STANDARDS-BACKLOG.md](./STANDARDS-BACKLOG.md)).

## When you add a backlog item

1. Amend this file and [SCHEMA-DSL.md](./SCHEMA-DSL.md).
2. Extend `EntitySchemaLoader` + tests (`tests/Unit/Schema/`, `tests/Feature/MakeCrudSchemaTest.php`).
3. Update [schemas/examples/](../schemas/examples/) if it helps agents.
4. Move the row from **Next** to **Shipped (vN)** in this file and sync [GENERATOR-ROADMAP.md](./GENERATOR-ROADMAP.md).
