# Production defaults (new apps)

**Product rule:** [AUTHENTICATION.md](./AUTHENTICATION.md) — the **package** keeps auth config at `null` so existing apps stay compatible; **`bin/new-app`** (without **`--demo`**) applies secure defaults (Sanctum, `route_auth`, web auth, permissions example). Use **`--demo`** only when you explicitly want a permissive sandbox.

The checklist below applies when you bootstrap manually or tune after `bin/new-app`.

## Checklist (new Laravel app)

1. **Publish config** — `php artisan vendor:publish --tag=blueprint-config`
2. **Set auth** — `route_auth` and `route_auth_web` below (after Sanctum or your guard is wired)
3. **Install profile** — `php artisan engineering:install --profile=…` with **`--permissions`** when you use permission-backed CRUD
4. **Generate CRUD** — `make:crud … --permissions` (not `--open-policy`); add `--api-envelope` only if you want `{ data, meta }` on every generated JSON endpoint
5. **Idempotency** — `->middleware('idempotent')` on critical POSTs only (not default CRUD `store`)

## `config/blueprint.php`

```php
'route_auth' => 'sanctum',      // API modules: make:module --api picks up auth:sanctum
'route_auth_web' => 'auth',     // Blade CRUD: session auth on generated web routes
'api_envelope' => false,        // true only if every JSON endpoint should use ApiResponse { data, meta }
```

Install Sanctum (or your token guard) and register `auth:sanctum` before relying on `route_auth`.

## CRUD generation

```bash
php artisan make:crud Orders Order --schema=orders/order.yaml --api --web --permissions
```

- **`--permissions`** — production-safe policies (not `--open-policy`).
- **`--auth=sanctum`** — per-command API auth when you do not set `route_auth` globally.
- **`--auth=auth`** — adds session `auth` to generated **web** routes when `--web` is used.

## Idempotency

Apply middleware only on critical POST routes (payments, transfers):

```php
Route::post('/transfers', TransferController::class)->middleware('idempotent');
```

See [IDEMPOTENCY.md](./IDEMPOTENCY.md).

## Greenfield via `bin/new-app`

Unless you pass **`--demo`**, the script installs spatie/laravel-permission and **laravel/sanctum**, runs `engineering:install --permissions`, sets **`route_auth` => `sanctum`** and **`route_auth_web` => `auth`**, generates the example Catalog CRUD with **`--permissions`**, and runs **`permissions:sync`**. Generated API tests use **`Best\Blueprint\Testing\ActsAsApiUser`** (`Sanctum::actingAs` when routes use `auth:sanctum`).

## After install

Modular / SaaS profiles: run migrations, `permissions:sync`, and add auth middleware is automatic once `route_auth` is set.

Full audit checklist: [ENGINEERING-AUDIT.md](./ENGINEERING-AUDIT.md).
