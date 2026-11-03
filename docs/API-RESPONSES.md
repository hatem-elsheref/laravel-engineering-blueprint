# API responses standard

## Current generator behavior (`make:crud --api`)

LEB uses **Laravel API Resources** as shipped:

- Single record: JsonResource wrapping the model (`stubs/resource.stub`).
- Collection: `Resource::collection()` with default Laravel pagination meta when using `paginate()`.
- Status codes: **200** OK, **201** Created (store), **204** No Content (delete), **403** Forbidden, **404** Not Found, **422** Validation Error.

By default, generated controllers return Laravel **JsonResource** responses directly.

**Opt-in envelope:** `php artisan make:crud … --api-envelope` or `config('blueprint.api_envelope') => true` uses `Best\Blueprint\Http\ApiResponse` and `controller-api-envelope.stub`:

```json
{
  "data": { … },
  "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 42 }
}
```

Manual use in app code:

```php
use Best\Blueprint\Http\ApiResponse;

return ApiResponse::created(new OrderResource($order));
return ApiResponse::collection(OrderResource::collection($paginator));
```

## Recommended contract (applications)

When you standardize at app level (see [STANDARDS-BACKLOG.md](./STANDARDS-BACKLOG.md)):

| Case | HTTP | Body |
|------|------|------|
| Success (single) | 200 / 201 | Resource or `{ "data": { ... } }` |
| Success (list) | 200 | `{ "data": [...], "meta": { "current_page", ... } }` |
| Validation | 422 | `{ "message", "errors": { field: [...] } }` (Laravel default) |
| Unauthorized | 401 | Consistent message; no stack traces |
| Forbidden | 403 | No internal policy names in production |
| Not found | 404 | Missing model or deny-as-not-found (`--owned`) |
| Business rule | 422 or 409 | App-defined; document per module |

**Never** expose SQL, stack traces, or secrets in JSON responses.

## Versioning

Prefer URL prefix convention (`/api/v1/...`) in new apps; register module routes under the chosen prefix in `Provider.php`. The generator does not force a version segment — add it when the app adopts versioning.

## Resources

- Keep Resources **presentation-only** — no queries or business rules.
- Conditional fields: `$this->when(...)` / `$this->mergeWhen(...)`.
- Hide sensitive columns on the model (`$hidden`) and avoid leaking them in Resources.

## Pagination

Generated list queries use `Best\Blueprint\Support\ListPerPage` with `config('blueprint.pagination.per_page')` (default **15**) and `max_per_page` (default **100**). API `index` actions pass `?per_page=` when the controller receives `Request` (all generated JSON list endpoints). Override in config or in the Query class when a module needs a different cap.

## API envelope — project decision

| Choice | When | Config / flag |
|--------|------|----------------|
| **Laravel default** | Public APIs, mobile clients that expect Laravel pagination meta on resources | `api_envelope` => false (package default) |
| **`{ data, meta }` envelope** | One JSON shape for every endpoint; SPA/admin dashboards | `api_envelope` => true or `make:crud --api-envelope` |

Pick one per app and document it in the app README; do not mix envelopes on the same API surface without versioning.

## Authentication

See **[AUTHENTICATION.md](./AUTHENTICATION.md)** — `make:crud` does not force auth; `bin/new-app` (without `--demo`) bootstraps secure route auth; package config stays `null` for backward compatibility.
