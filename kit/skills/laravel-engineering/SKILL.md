---
name: laravel-engineering
description: Use when building or reviewing Laravel modules, actions, DTOs, tenancy (stancl), tenant features/settings, or aligning code with the Laravel Engineering Blueprint Phase 0 specs.
---

# Laravel Engineering Blueprint

## Authority

Phase 0 constitution lives in the project bundle:

1. `docs/PHASE-0.md`
2. `docs/ARCHITECTURE.md`
3. `docs/NAMING.md`
4. `docs/TENANCY.md`
5. `docs/FEATURES.md`
6. `docs/SETTINGS.md`

If Phase 0 is not approved, do not implement application code for features, tenancy services, or modules—only doc amendments.

## Architecture defaults (from ARCHITECTURE.md)

- **Modules:** `app/Modules/{Name}/` with only needed folders (`Actions`, `Data`, `Requests`, `Controllers`, `routes/`, `Provider.php`, …).
- **Shared:** `app/Shared/Features`, `Settings`, `Support`, `Contracts`.
- **Controller** — request/response only.
- **Request** — validation + authorization.
- **Data** — typed transfer (native PHP DTOs).
- **Action / Query** — write vs read operations.
- **Flow:** Route → Middleware → Request → Data → Controller → Action|Query → Model|Service → Resource|View (use only needed layers).

## Naming

Follow `docs/NAMING.md`. Folder implies type (`Actions/CreateOrder.php`).

## Tenancy & features & settings

- `docs/TENANCY.md` — central vs tenant DB, `tenant:create`, migration paths, isolation.
- `docs/FEATURES.md` — tenant `features` table, `Feature::enabled()`, platform admin only writes.
- `docs/SETTINGS.md` — tenant `settings` table, `Setting::get` / `Setting::set`.

## Phase 0 vs later phases

- **Phase 0:** specifications only.
- **Phase 1+:** Laravel app, stancl wiring, Shared services (implementation).

Full narrative: `rules.md` at kit root.
