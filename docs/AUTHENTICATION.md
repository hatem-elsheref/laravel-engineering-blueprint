# Authentication — product model

LEB treats **policies** and **route middleware** separately. Policies default to deny-by-default; **routes do not require a signed-in user unless you opt in**. That split is intentional.

## Two layers (keep both in mind)

| Layer | Default | Purpose |
|-------|---------|---------|
| **Package** (`config/blueprint.php` in any app) | `route_auth` and `route_auth_web` => **null** | Adding the package to an **existing** Laravel app must not break routes or tests. |
| **Greenfield** (`bin/new-app`) | **Secure bootstrap** unless you pass **`--demo`** | New projects should start production-oriented; demo is an explicit opt-out. |

Do not expect `composer require best/laravel-engineering-blueprint` alone to turn on Sanctum or session auth. Do expect **`bin/new-app`** (without `--demo`) to install Sanctum, set `route_auth` / `route_auth_web`, and generate the example CRUD with **`--permissions`**.

## What `make:crud` does

- **Does not** add `auth` or `auth:sanctum` on routes when config is `null` and you omit **`--auth`**.
- **Does** generate deny-by-default (or `--permissions` / `--open-policy`) policies and tests that match the route auth you chose.

Enable middleware in any of these ways (first matching source wins per command — see `RouteAuth`):

1. **`config('blueprint.route_auth')`** — e.g. `'sanctum'` → `auth:sanctum` on generated API routes and on **`make:module --api`** route groups.
2. **`config('blueprint.route_auth_web')`** — e.g. `'auth'` → session auth on generated web CRUD and **`make:module --web`** groups.
3. **Per command** — `make:crud … --auth=sanctum` (API) or `--auth=auth` (web when `--web`).

Generated API tests use **`Best\Blueprint\Testing\ActsAsApiUser`**: when routes use `auth:sanctum`, tests call `Sanctum::actingAs` (set `$this->lebApiSanctum = true` or rely on config). Requires **`laravel/sanctum`** when using Sanctum.

## Greenfield: secure by default

**`bin/new-app /path/to/app`** (no `--demo`):

- Installs **laravel/sanctum** and **spatie/laravel-permission** (for the example).
- Runs **`engineering:install --permissions`** when the example uses permissions.
- Sets **`route_auth` => `sanctum`**, **`route_auth_web` => `auth`** in `config/blueprint.php`.
- Example Catalog CRUD uses **`--permissions`** (not `--open-policy`); API tests align with Sanctum.

**`bin/new-app … --demo`** — explicit sandbox: `--open-policy`, no permission stack, auth config left at package nulls. Use only for quick local demos.

You still need a **session login** stack (Breeze, Jetstream, etc.) for Blade `auth` middleware to succeed in the browser.

## Existing apps

1. Publish config: `php artisan vendor:publish --tag=blueprint-config`.
2. Install Sanctum (or your API guard) and wire session auth if you use web CRUD.
3. Set `route_auth` / `route_auth_web` (see [PRODUCTION-DEFAULTS.md](./PRODUCTION-DEFAULTS.md)), or run **`php artisan engineering:install --profile=modular --secure`** on a fresh config (only replaces keys that are still `null`).
4. Regenerate or hand-edit module `Provider.php` / route files; use **`make:crud --auth=…`** for new CRUD.

## Summary

| Question | Answer |
|----------|--------|
| Should plain `make:crud` force auth? | **No** — opt-in via config or `--auth`. |
| Should a **new** LEB app be secure? | **Yes** — use **`bin/new-app`** without **`--demo`**, or copy its config steps. |
| Is `--demo` the supported way to disable that? | **Yes** — intentional, documented opt-out. |

Related: [SECURITY-GENERATION.md](./SECURITY-GENERATION.md), [PRODUCTION-DEFAULTS.md](./PRODUCTION-DEFAULTS.md), [API-RESPONSES.md](./API-RESPONSES.md#authentication).
