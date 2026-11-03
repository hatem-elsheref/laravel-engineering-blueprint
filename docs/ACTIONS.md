# Actions and services standard

## Action

One class = **one business operation** (create order, cancel shipment, sync inventory).

| Rule | Detail |
|------|--------|
| Entry point | Single `handle(...)` (or `execute` if your module already uses that name — stay consistent within a module) |
| Input | Typed: Data DTO, models, primitives — **not** `Request` |
| Output | Model, bool, DTO, or void — **not** HTTP responses |
| Side effects | Explicit; use DB transactions when multiple writes must commit together |
| HTTP | Forbidden inside Actions |

### Generator

- `php artisan make:action {Module} {Name}` — optional `--data=`, `--transaction`.
- `make:crud` emits Create / Update / Delete actions wired from the controller.

### Transactions

Use `make:action --transaction` or wrap in `DB::transaction()` when:

- Multiple tables must stay consistent.
- You publish domain events after commit.

Do **not** wrap every single-row update by default.

## Service

Introduce a **Service** only when logic is **shared across several Actions** or is substantial enough to deserve its own class ([ARCHITECTURE.md](./ARCHITECTURE.md) §1.3).

Anti-patterns:

- One `OrderService` with twenty unrelated methods.
- Services that only forward to Eloquent without adding rules.

Prefer a dedicated Action per use case; extract a Service when duplication proves real.

## Controller wiring

```text
Form Request → Controller → Action → Model
```

Queries for reads: `Controller → Query → Model` (paginated lists).

## Testing

- Feature tests: HTTP → database outcomes (primary for CRUD).
- Unit tests: Action with mocked dependencies when logic is branch-heavy or risky.
