# Engineering standards backlog

Gap between [rules.md](../rules.md) vision and current package output. Items are classified by **how** they should ship.

## Priority: High (framework / Standard first)

| Feature | Ship as | Notes |
|---------|---------|--------|
| Consistent API response structure | **Shipped:** `Best\Blueprint\Http\ApiResponse` + `--api-envelope` / `blueprint.api_envelope` | Validation 422 unchanged; optional `errors` envelope later |
| Pagination standards | **Offset shipped** — `ListPerPage`, `max_per_page`, `?per_page=` ([HARDENING.md](./HARDENING.md) §3) | Cursor pagination still backlog |
| Idempotency for critical POSTs | **Shipped (HTTP layer)** — `ReplayIdempotentRequests` + [IDEMPOTENCY.md](./IDEMPOTENCY.md) | 409 on payload mismatch, cache lock, query in fingerprint; still pair with DB uniqueness for money/stock |
| Database indexing conventions | Doc + schema DSL | FK indexes, composite rules |
| Transaction management | Action convention + `--transaction` | Already on `make:action`; document when mandatory |
| Authorization conventions | PERMISSIONS + deny-default policy | See [SECURITY-GENERATION.md](./SECURITY-GENERATION.md) |
| Reusable query filters & sorting | Query helpers + list Actions | Spatie query builder optional |

## Priority: Medium

| Feature | Ship as |
|---------|---------|
| API versioning | Doc + route prefix convention |
| AJAX searchable selects | Frontend standard + PageData contract |
| Rate limiting | Doc + route middleware templates |
| Audit logging | Optional module / trait |
| Background jobs & retries | Job stubs + doc |

## Priority: Later

| Feature | Ship as |
|---------|---------|
| Domain events | Event/listener stubs + boundary rules |

## Not in CRUD generator alone

- Idempotency, API envelope, rate limits → **Shared** + **docs**
- Module boundaries → **architecture tests** + [MODULE-BOUNDARIES.md](./MODULE-BOUNDARIES.md)
- Tenancy strategies → [TENANCY-EXTENSIBILITY.md](./TENANCY-EXTENSIBILITY.md)

Update this table when an item moves to “implemented” with a link to code or spec.
