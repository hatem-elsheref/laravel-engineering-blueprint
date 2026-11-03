# Module boundaries

Part of the Engineering Standard. Folder layout under `app/Modules/` is necessary but **not sufficient** for modularity.

## Ownership

| Owned by module | Not owned by module |
|-----------------|---------------------|
| Its models, migrations (via global paths), policies | Another module’s internal models |
| Its Actions, Queries, Services | Another module’s Requests/Controllers |
| Its routes and HTTP adapters | Direct use of another module’s “private” classes |
| Its domain events | Circular imports between modules |

## Dependency direction

```text
HTTP (Controllers, Requests)
        ↓
Application (Actions, Queries)
        ↓
Domain (Models, domain Services, Policies)
        ↓
Infrastructure (when needed: external APIs, storage adapters)
```

**Shared/** holds cross-cutting capabilities with a **named reason** (Features, Settings, Support helpers, Contracts). Do not dump unrelated logic into Shared to avoid thinking.

## Cross-module communication

Allowed (prefer in this order):

1. **Public contract** in `app/Shared/Contracts/` (interface + DTO).
2. **Public Action or Query** explicitly documented as module API (small surface).
3. **Domain events** — publisher does not know subscribers’ internals.
4. **Read models / projections** when eventual consistency is acceptable.

Forbidden:

- Importing another module’s `Requests`, `Controllers`, or internal `Services`.
- Reaching into another module’s Eloquent models from Actions (except through a contract or shared kernel API).
- Shared database assumptions (hidden joins across bounded contexts) without an explicit integration layer.

**Example:** Orders must not depend on Inventory’s internal tables. It calls `ReserveStock` (contract or Inventory’s public Action) or listens to `StockReserved`.

## Services and repositories

- **Action** — one use case; default write path.
- **Query** — one read/search use case; default read path.
- **Service** — only when logic is reused **inside the same module** or implements a **Shared contract**.
- **Repository** — only when Eloquent is insufficient (complex legacy SQL, external store). Not a default CRUD layer.

## Enforcement

```bash
php artisan engineering:architecture
```

Scans `blueprint.module_path` (or `--path=`) for cross-module `use` imports that reach forbidden segments (Controllers, Requests, Models, Services, Policies, Resources, Http, Data). **Allowed** cross-module segments: Actions, Queries, Events, Jobs.

Tune lists in `config/blueprint.php` → `architecture.forbidden_cross_module_segments` / `allowed_cross_module_segments`.

Agents and CI should run this after module changes ([AI-WORKFLOW.md](./AI-WORKFLOW.md)).

Pest regression: `ModuleDependencyGraphTest` against the golden fixture ([DEPENDENCY-GRAPH.md](../tests/Fixtures/module-boundaries/DEPENDENCY-GRAPH.md)).

### PHPStan (deeper than `use` lines)

Include the package extension in your app's `phpstan.neon` (or `phpstan.dist.neon`):

```neon
includes:
    - vendor/best/laravel-engineering-blueprint/phpstan/blueprint-module-boundaries.neon
```

Rules flag cross-module references in code: **`new`**, **static calls**, **`::class`**, and **extends/implements** (including FQCNs with no `use` statement). Line **`use` imports** are covered by `engineering:architecture`. Defaults match `config/blueprint.php` → `architecture.*`; copy `phpstan/blueprint-module-boundaries.neon` if you need different segment lists.

See [ARCHITECTURE.md](./ARCHITECTURE.md) §1.4 for baseline module rules.
