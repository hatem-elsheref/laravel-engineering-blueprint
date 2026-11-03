# Security defaults for generated code

## Current behavior

| Area | Behavior |
|------|----------|
| **Policy** (default, no flags) | **Deny-by-default** for all abilities; guests and signed-in users get 403 until policy is implemented |
| **Policy** (`--open-policy`) | Demo only: every **authenticated** user passes; **guests** denied |
| **Policy** (`--permissions`) | Checks named permissions; guests denied; see [PERMISSIONS.md](./PERMISSIONS.md) |
| **Policy** (`--owned`) | Row ownership + optional `.any` permission |
| **Routes** | Optional `blueprint.route_auth` / `make:crud --auth=sanctum` on API; `blueprint.route_auth_web` / `--auth=auth` on web CRUD; module `Provider.php` picks up both when config is set ([PRODUCTION-DEFAULTS.md](./PRODUCTION-DEFAULTS.md)) |
| **Features** | Optional `--feature=` middleware on routes |
| **Tests** | Guest denial; authenticated success (open policy); 404 vs 403 rules for missing records |

The default stub **denies every ability** until you implement the policy or choose **`--permissions`** / demo **`--open-policy`**.

## Still planned

- **Web CRUD auth** — session `auth` on `Route::resource` when you add it manually or extend the generator.
- **Broader tenancy regression** — jobs, schedules beyond CRUD ([QUALITY-ROADMAP.md](./QUALITY-ROADMAP.md)).

## Provider comments (shipped)

`make:module --api` emits a comment in `Provider.php` reminding you to add authentication middleware before production.

## What developers should do

- **Production:** `engineering:install --permissions` + `make:crud --permissions` (+ `--owned` when needed), auth middleware on routes, `permissions:sync`.
- **Local demo without permissions setup:** `make:crud ... --open-policy` (not for production).
- **Default `make:crud`:** implement `*Policy` or switch to `--permissions`.
