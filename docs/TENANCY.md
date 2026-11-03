# TENANCY.md

Part of [Phase 0 — Architecture Foundation](./PHASE-0.md).

**Note:** Database-per-tenant (this doc) is the default **tenant-saas** profile, not the only tenancy strategy long-term. See [TENANCY-EXTENSIBILITY.md](./TENANCY-EXTENSIBILITY.md).

## 3.1 Package

Use:

`stancl/tenancy`

Official documentation:

https://tenancyforlaravel.com/

Use the package's supported tenant identification, database bootstrapping, database creation, and migration features.

Version: `stancl/tenancy ^3.10`. Set it up with:

```bash
composer require stancl/tenancy:^3.10
php artisan engineering:install --profile=tenant-saas
php artisan migrate
```

Do not build a custom tenancy engine unless a documented requirement cannot be handled by the package.

## 3.2 Database Design

The application supports multiple tenants and multiple databases.

Each tenant has its own database.

```text
Central Database
│
├── tenants
└── domains

Tenant A Database
│
├── users
├── features
├── settings
├── orders
├── products
└── inventory

Tenant B Database
│
├── users
├── features
├── settings
├── orders
├── products
└── inventory
```

## 3.3 Central Database

The central database stores only the information needed to identify and manage tenants.

Required information:

- Tenant ID
- Tenant identification data
- Domain records
- Tenant database name
- Package-required tenant metadata

Do not store tenant business data in the central database.

Examples of data that must stay in tenant databases:

- Tenant users
- Orders
- Products
- Inventory
- Features
- Settings
- Tenant roles and permissions (spatie tables in every tenant database, from `Permissions`; the central database has its own, from `CentralPermissions`; see PERMISSIONS.md)

## 3.4 Tenant Identification

Use subdomain identification.

Examples:

```text
app.example.com
acme.example.com
beta.example.com
```

The tenant domain must resolve to exactly one tenant.

The central application must be able to distinguish platform routes from tenant routes.

Custom domains can be supported later without changing the tenant database design.

Base domain: tenants are `<name>.<base>`. Set the base with `BLUEPRINT_TENANT_DOMAIN` (config `blueprint.tenant_domain`); when empty, the first central domain that is not an IP address is used (`localhost` if there is none). A `--domain` given to `tenant:create` must be a subdomain of the base or of a central domain. The default database name is `tenant_<name>` with dashes turned into underscores. Central domains are listed in `config/tenancy.php` (`central_domains`).

Route groups:

| Module | Created with | Middleware group | Answers on |
|---|---|---|---|
| Tenant module | `make:module Orders` | `tenant` (`InitializeTenancyByDomain` + `PreventAccessFromCentralDomains`) | Tenant domains |
| Central (platform) module | `make:module Plans --central` | `central` | Central domains only |

- Tenant module routes are registered after central routes.
- Central module route names start with `central.` (`central.plans.index`, `central.api.plans.index`).
- Unknown tenant domains return 404.
- Web sessions are tied to the tenant (or central app) where they started; the same cookie on another tenant is refused.
- The app's own `routes/web.php` and `routes/api.php` answer on every domain. Wrap central-only routes in `Route::domain(...)` or move them into a `--central` module.

## 3.5 Tenant Creation

Create tenants through a central Artisan command.

Example:

```bash
php artisan tenant:create acme \
    --domain=acme.example.com \
    --database=tenant_acme
```

Defaults: domain `acme.<base>`, database `tenant_acme`.

The command must:

1. Validate the tenant name.
2. Validate the domain.
3. Check for duplicate domains.
4. Create the tenant record.
5. Create the domain record.
6. Create the tenant database.
7. Run tenant migrations.
8. Seed default tenant data.
9. Create default features.
10. Create default settings.
11. Report the final result.

When permissions are set up, a "Sync permissions" step runs `Permissions` into the new tenant (after the migrations). The permission cache is isolated per tenant (key `<cache key>.tenant.<id>`, plus an in-memory reset when tenancy starts and ends), with or without the cache bootstrapper.

Notes on the implementation:

- Step 8 runs the seeder in `blueprint.tenant_seeder` (default `Database\Seeders\TenantSeeder`) when that class exists.
- Step 9 inserts the keys from `Features::defaults()`.
- Step 10 is reported as skipped: defaults come from the Settings definitions, so no rows are needed.
- The command refuses the central database, another tenant's database, and a database that already exists unless `--existing-database` is passed.

The process must be safe to retry after a failure. Re-running the same command skips finished steps.

Do not create tenants using a normal tenant-facing CRUD endpoint.

## 3.6 Migration Paths

Use Laravel's standard migration locations.

```text
database/
├── migrations/
│   ├── central migrations
│   └── ...
│
├── migrations/tenant/
│   ├── tenant migrations
│   └── ...
│
├── factories/
└── seeders/
```

Rules:

- Central migrations run on the central connection.
- Tenant migrations run through the tenancy package.
- Do not create migration folders inside modules.
- Use separate commands for central and tenant migrations.
- Never run tenant migrations against the central database.

Commands: `php artisan migrate` (central) and `php artisan tenants:migrate` (all tenants). `make:crud` in a tenant module writes its migration to `database/migrations/tenant/`; in a `--central` module, to `database/migrations/`.

## 3.7 Tenant Isolation

- Resolve the tenant before accessing tenant data.
- Use the package's database switching mechanism.
- Never trust a tenant ID sent by the browser.
- Never expose another tenant's database.
- Tenant users must not access central administration routes.
- Test database isolation.
- Test tenant switching in queued jobs and scheduled tasks.

## 3.8 Runtime Notes

- **Queue:** the `database` queue connection is pinned to the central connection, so workers find jobs dispatched inside a tenant.
- **Scheduler:** run tenant work with `php artisan tenants:run <command>`.
- **Cache:** tenant cache isolation needs a taggable store. Set `CACHE_STORE=redis` (or `memcached`); the `database` and `file` stores fail inside tenants.
- **Tests:** use `Best\Blueprint\Testing\InteractsWithTenancy` (`createTestTenant()`, `tenantUrl()`). Generated tenant tests use it and run against a real test tenant.
