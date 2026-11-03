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
- On replay with the **same payload**, return the stored result.
- On replay with a **different payload**, reject with **409 Conflict** (apps should enforce; the package middleware keys on body hash so mismatches miss the cache).
- Use **transactions + unique constraints** on `(tenant_id, idempotency_key)` to win races.

**Shipped (optional):** `Best\Blueprint\Http\Middleware\ReplayIdempotentRequests`, router alias **`idempotent`**. Config: `blueprint.idempotency.header`, `blueprint.idempotency.ttl`. Replays **2xx/3xx** responses only; apply on selected routes — not on every CRUD `POST`.

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
