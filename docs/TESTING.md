# Testing standard

LEB uses **Pest** and Laravel’s testing stack. Generated CRUD includes API and/or web feature tests.

## Layers

| Layer | Use for |
|-------|---------|
| **Unit** | Pure logic: policies helpers, schema loader, boundary policy, Data mapping |
| **Feature** | HTTP, authorization, validation, tenancy host, generated routes |
| **Integration / e2e** | Full install profiles (`bin/e2e`), snapshot regression |

Package gate: `composer check` (Pint, PHPStan level 6 on `src/`, Pest). **MySQL integration tests are not part of `composer check`** — they run in a separate CI job.

## MySQL integration (production-like)

Path: `tests/MysqlIntegration/` (transactions, unique indexes, foreign keys, schema DSL migrations, tenant DB isolation + queued jobs, `permissions:sync`, idempotency with `database` cache + locks). Kit auth parity: `tests/Unit/Kit/KitAuthParityTest.php`.

Local (MySQL 8+):

```bash
export LEB_MYSQL=1
export LEB_MYSQL_DATABASE=leb_central
export LEB_MYSQL_USERNAME=root
export LEB_MYSQL_PASSWORD=root
composer test:mysql
```

Without `LEB_MYSQL=1`, those tests **skip**. CI: GitHub Actions job **mysql-integration** (MySQL service container). Includes `TenancyScheduleMysqlTest` (`tenants:run`).

**Idempotency stress:** `composer test:stress` (pcntl overlap; runs in CI matrix after `composer check`). Stub matrix snapshot: `tests/Permissions/StubMatrixEnvelopeSnapshotTest.php` (`modular-api-envelope`).

## Generated CRUD tests

Default (`policy.stub` deny-all):

- Guests receive **403** on protected routes.
- Authenticated users receive **403** until policy is implemented or `--permissions` / `--open-policy` is used.

With `--permissions`:

- Use `Best\Blueprint\Testing\InteractsWithPermissions` (`grantPermissions`, `makeSuperAdmin`).
- Assert **403** without permission and **200/201** with permission.

With `--owned`:

- Another user’s record → **404** (not 403) when policy uses deny-as-not-found.

Always assert **422** for missing required fields on store/update where applicable.

## Authorization

- Never assume “logged in” implies access — test denied and allowed paths.
- Super-admin `Gate::before` bypasses checks — document in [PERMISSIONS.md](./PERMISSIONS.md); test non-super-admin paths for real permissions.

## Multi-tenancy

- Prove tenant data lives in tenant DB; central host does not serve tenant CRUD ([TENANCY.md](./TENANCY.md)).
- Cross-tenant: user on tenant B must not see tenant A’s rows ([TenantCrudEndToEndTest.php](../tests/Tenancy/TenantCrudEndToEndTest.php)).

## Snapshots

CRUD output snapshots: update with `UPDATE_CRUD_SNAPSHOTS=1` only when generator changes are intentional.

## Agent workflow

After code changes:

1. `composer check`
2. For generator/tenancy/install changes: `bin/e2e`
3. For boundary changes: `engineering:architecture` in a modular app + optional PHPStan neon include

Report failures honestly; do not weaken assertions to go green.
