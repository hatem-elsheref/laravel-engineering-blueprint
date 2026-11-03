# TENANCY.md

Part of [Phase 0 — Architecture Foundation](./PHASE-0.md).

## 3.1 Package

Use:

`stancl/tenancy`

Official documentation:

https://tenancyforlaravel.com/

Use the package's supported tenant identification, database bootstrapping, database creation, and migration features.

The exact installed package version must be verified against the project's Composer dependencies before implementation.

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
- Tenant roles and permissions

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

## 3.5 Tenant Creation

Create tenants through a central Artisan command.

Example:

```bash
php artisan tenant:create acme \
    --domain=acme.example.com \
    --database=tenant_acme
```

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

The process must be safe to retry after a failure.

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

## 3.7 Tenant Isolation

- Resolve the tenant before accessing tenant data.
- Use the package's database switching mechanism.
- Never trust a tenant ID sent by the browser.
- Never expose another tenant's database.
- Tenant users must not access central administration routes.
- Test database isolation.
- Test tenant switching in queued jobs and scheduled tasks.
