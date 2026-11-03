# Security defaults for generated code

**Authentication on routes** (Sanctum, session `auth`) is **opt-in** on the package; **greenfield apps** bootstrap secure via `bin/new-app`. Read [AUTHENTICATION.md](./AUTHENTICATION.md) for the full product model.

## Current behavior

| Area | Behavior |
|------|----------|
| **Policy** (default, no flags) | **Deny-by-default** for all abilities; guests and signed-in users get 403 until policy is implemented |
| **Policy** (`--open-policy`) | Demo only: every **authenticated** user passes; **guests** denied |
| **Policy** (`--permissions`) | Checks named permissions; guests denied; see [PERMISSIONS.md](./PERMISSIONS.md) |
| **Policy** (`--owned`) | Row ownership + optional `.any` permission |
| **Routes** | **Not authenticated by default** (`route_auth` / `route_auth_web` => null). Enable via config, `make:crud --auth=…`, or greenfield `bin/new-app` ([AUTHENTICATION.md](./AUTHENTICATION.md), [PRODUCTION-DEFAULTS.md](./PRODUCTION-DEFAULTS.md)) |
| **Features** | Optional `--feature=` middleware on routes |
| **Tests** | Guest denial; authenticated success (open policy); 404 vs 403 rules for missing records |

The default stub **denies every ability** until you implement the policy or choose **`--permissions`** / demo **`--open-policy`**.

## Still planned

- **Broader tenancy regression** — jobs, schedules beyond CRUD ([QUALITY-ROADMAP.md](./QUALITY-ROADMAP.md)).

## Provider comments (shipped)

`make:module --api` emits a comment in `Provider.php` reminding you to add authentication middleware before production.

## What developers should do

- **Production:** `engineering:install --permissions` + `make:crud --permissions` (+ `--owned` when needed), auth middleware on routes, `permissions:sync`.
- **Local demo without permissions setup:** `make:crud ... --open-policy` (not for production).
- **Default `make:crud`:** implement `*Policy` or switch to `--permissions`.
