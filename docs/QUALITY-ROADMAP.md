# Quality & CI roadmap

## Current foundations

- PHPStan level 6
- Module-boundary PHPStan rules (`phpstan/blueprint-module-boundaries.neon`) — `use`, `new`, static calls, class hierarchy
- Laravel Pint
- Pest
- CI matrix: PHP 8.3 / 8.4, Laravel 12 / 13
- End-to-end tests across install profiles and CRUD snapshots

Treat green CI on the **default branch** as the release gate; matrix jobs can fail independently — verify before claiming full compatibility.

## Planned additions

| Area | Purpose |
|------|---------|
| Upgrade tests | Install from previous package version, run migrations/commands |
| Production-like DB | MySQL/PostgreSQL in CI, not SQLite-only where behavior differs |
| Tenancy security tests | Cross-tenant CRUD by ID (**shipped** in `TenantCrudEndToEndTest`); extend to jobs, scheduled tasks |
| Generated-code regression | Schema/profile changes cannot silently alter snapshots |
| Published stub compatibility | Apps with `vendor:publish --tag=blueprint-stubs` tested against new releases |
| Permission / policy regression | `--permissions` and `--owned` snapshots stay stable |

Link implementation issues to [ROADMAP.md](./ROADMAP.md) track 3.
