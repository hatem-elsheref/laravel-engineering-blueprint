# Generator roadmap

Current `make:crud --fields=` supports a **small type system** (`string`, `int`, `float`, `bool`, `array`, `Carbon\CarbonImmutable`, nullable `?`). That is enough for demos and simple entities, not for most production domains.

## Direction: schema-first, not one giant command

Prefer a **schema definition** (file or interactive wizard) that drives:

- Migration (columns, indexes, FKs, soft deletes)
- Model (casts, relationships, scopes)
- Factory
- Validation rules
- API Resource shape
- Tests

CLI: `make:crud Orders Order --schema=orders/order.yaml` — **v1 shipped** (see [SCHEMA-DSL.md](./SCHEMA-DSL.md)).

## Capability backlog

| Capability | Why | Delivery |
|------------|-----|----------|
| Foreign keys & relationships | Real entities | **v1:** schema → migration + `belongsTo` |
| Enums | Domain values | **v1:** PHP enum + validation |
| Decimal precision | Money, qty | **v1:** `precision` / `scale` in schema |
| Soft deletes | Lifecycle | **v1:** schema flag + migration |
| Model scopes | Reusable queries | **v2:** `scopes` in schema |
| Field rules / column unique | Validation + DB | **v2:** `min`, `max`, `regex`, field `unique` |
| Casts, defaults, hasMany | Model + migration + factory | **v3:** field `cast`/`default`, entity `relations` → [SCHEMA-BACKLOG.md](./SCHEMA-BACKLOG.md) |
| Custom casts | JSON, value objects | Schema → cast list |
| Unique constraints | Integrity | **v1:** schema → migration |
| Composite indexes | Performance | **v1:** schema → migration |
| Nested resources | Complex APIs | **v6:** `make:nested-crud` / `--nested-under` |
| Custom validation rules | Business rules | **v5:** field `rules:` FQCN list |

Avoid stuffing all options into `make:crud` flags; group by **schema** and **profiles** (`basic`, `catalog`, `transactional`, …).

## Security & auth (generator)

See [SECURITY-GENERATION.md](./SECURITY-GENERATION.md).

**Today:** without `--permissions`, policy is **deny-by-default**; use `--open-policy` for demos or `--permissions` for production. Auth middleware (e.g. `auth:sanctum`) is documented on module `Provider.php`, not auto-applied to every route.

**Shipped:**

- Deny-by-default policies (default `make:crud`).
- `--open-policy` for demos only.
- `--permissions` remains the production path.
- Generated tests include tenant isolation cases for `tenant-saas`.
- Explicit auth middleware templates in module provider stub.

## Regression safety

- Keep [CrudSnapshotTest](../tests/Feature/CrudSnapshotTest.php) and profile fixtures.
- Add **generated-code regression** tests when schema DSL lands.
- Document stub publish/customize flow in README.

## Relation to Standard

High-priority **runtime** standards (pagination, API envelope, idempotency) live in [STANDARDS-BACKLOG.md](./STANDARDS-BACKLOG.md) as Shared components, not only in CRUD output.
