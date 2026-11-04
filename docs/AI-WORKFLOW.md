# AI engineering workflow (target)

Part of the **AI Engineering Kit** ([PRODUCT.md](./PRODUCT.md)). Agents should follow this loop; humans can use the same checklist in review.

## Steps

1. **Understand architecture** — Read [CONSTITUTION.md](./CONSTITUTION.md), [ENGINEERING-AUDIT.md](./ENGINEERING-AUDIT.md) (known gaps), profile from `config/blueprint.php`, [MODULE-BOUNDARIES.md](./MODULE-BOUNDARIES.md).
2. **Identify module** — Confirm target under `app/Modules/{Name}/` and central vs tenant module (`--central`).
3. **Read rules** — [DOCUMENT-HIERARCHY.md](./DOCUMENT-HIERARCHY.md); tenancy/features/settings/permissions as applicable.
4. **Generate code** — Use Artisan only (`make:module`, `make:dto`, `make:action`, `make:service`, `make:crud`); prefer `--permissions` for production paths.
5. **Run verification** — `composer test`, `./vendor/bin/phpstan`, `./vendor/bin/pint --test` (or project equivalents). Apps should include `vendor/best/laravel-engineering-blueprint/phpstan/blueprint-module-boundaries.neon` in PHPStan for cross-module references beyond `use` lines.
6. **Architecture check** — `php artisan engineering:architecture` (module boundaries); no business logic in controllers; migrations only in global paths.
7. **Report gaps** — List anything not generated safely (schema unsupported, auth not configured, missing idempotency, inventory transaction needed manual Action).

## Skills & install

Project bundle: `laravel-engineering-blueprint/kit/` via `bin/leb-install --link-agents`.

Skills: **laravel-engineering**, **leb-generate-crud** (must reference package commands, not hand-copy stubs).

## When to refuse

- Phase 0 spec conflict without amendment
- Generating CRUD for inventory/financial **transactional** flows without explicit Action design
- Storing tenant business data in central DB
- Enabling features only in UI without route/action enforcement

## Enforcement (future)

Automated step 6 via custom PHPStan rules and Pest architecture tests — see [ROADMAP.md](./ROADMAP.md).
