# Idempotency standard

Duplicate work causes double charges, duplicate rows, and inconsistent inventory. LEB separates **four layers** — use the right one; do not force idempotency on every CRUD endpoint.

## 1. UI duplicate submission

- Disable submit while the request is in flight.
- Re-enable after success or recoverable failure.
- **Does not** guarantee backend safety — always enforce server-side rules.

## 2. HTTP idempotency (critical POSTs)

For operations where **retrying the same request must not repeat the effect** (payments, transfers, external webhooks):

- Client sends **`Idempotency-Key`** (or app-defined header) stable per logical operation.
- Server scopes the key to **user + tenant + route** (or operation name).
- Store key → result (or reference) with **TTL** and cleanup.
- On replay with the **same payload** (body + query string), return the stored result.
- On replay with a **different payload**, reject with **409 Conflict** — enforced by `ReplayIdempotentRequests`.
- Use **transactions + unique constraints** on `(tenant_id, idempotency_key)` as a second line of defense for financial writes.

**Shipped (optional):** `Best\Blueprint\Http\Middleware\ReplayIdempotentRequests`, router alias **`idempotent`**. Config: `blueprint.idempotency.header`, `blueprint.idempotency.ttl`, `lock_seconds`, `wait_seconds`. Storage key = tenant + user + route + client key; stored entry includes a **fingerprint** (body + sorted query). While the first request runs, an **exclusive cache lock** prevents duplicate execution; the second waiter replays or gets 409. Requires a cache store that supports locks (**redis** recommended in production; `array` does not). Replays **2xx/3xx** only — apply on selected routes, not on every CRUD `POST`.

## 3. Database uniqueness

- Unique indexes, `unique` validation rules, schema field `unique: true` ([SCHEMA-DSL.md](./SCHEMA-DSL.md)).
- Prevents duplicate rows even under concurrent requests.
- Appropriate for natural keys (SKU, email per tenant).

## 4. Business-operation idempotency

- Domain rules: “cancel only once”, “allocate at most available qty”.
- Implement in **Actions** with row locks or optimistic locking as required.

## Ordinary CRUD

`POST /products` twice creates two products unless you add keys or business rules — that is **expected**. Use idempotency only where duplicates cause harm.

## Testing

When implementing HTTP idempotency:

- Same key + same body → same response, single side effect.
- Same key + different body → conflict.
- Concurrent duplicate keys → one winner, one safe replay or conflict.

Tenancy: keys must not leak across tenants.

Package coverage: `tests/Feature/Http/ReplayIdempotentRequestsTest.php` (SQLite + file cache locks); optional MySQL + `database` cache in `tests/MysqlIntegration/IdempotencyMysqlTest.php` when `LEB_MYSQL=1`.

## Production checklist (financial / critical POSTs)

Before shipping an idempotent route in production:

1. **Middleware** — Route uses the `idempotent` alias (or `ReplayIdempotentRequests`) only on endpoints where duplicate execution causes harm.
2. **Cache store** — Default cache driver supports **locks** (`redis` recommended; not `array`). Tune `blueprint.idempotency.lock_seconds` and `wait_seconds` for worst-case handler duration.
3. **Database** — For money or inventory, add a **unique** constraint on `(tenant_id, idempotency_key)` (or equivalent) so a cache miss cannot double-apply.
4. **Response policy** — Only **2xx/3xx** responses are stored for replay; document client behavior on 409 (payload mismatch).
5. **Tenancy** — Keys are scoped in `IdempotencyKey::storageKey()`; verify cross-tenant isolation in staging.
6. **Observability** — Log 409 fingerprint mismatches and lock timeouts; alert on spikes.

Optional load proof: two concurrent clients with the same key and body should observe **one** side effect (manual or k6/Artillery); CI does not require a parallel HTTP load test.
