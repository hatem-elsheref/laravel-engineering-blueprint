# Engineering constitution (agents)

**Authority:** [Phase 0 — Architecture Foundation](./PHASE-0.md). These specs are the project constitution. Code and generators must follow them unless an amendment is recorded in Phase 0 docs.

## Read in order

1. [ARCHITECTURE.md](./ARCHITECTURE.md)
2. [NAMING.md](./NAMING.md)
3. [TENANCY.md](./TENANCY.md)
4. [FEATURES.md](./FEATURES.md)
5. [SETTINGS.md](./SETTINGS.md)

Long-form discussion: [../rules.md](../rules.md).

## Phase 0 status

Phase 0 is **approved** ([PHASE-0.md](./PHASE-0.md)). The package `best/laravel-engineering-blueprint` implements it.

## Generate code with the commands

Use the Artisan commands; never copy or hand-fill stubs ([../README.md](../README.md)):

```bash
php artisan make:module Orders --api --web
php artisan make:dto Orders OrderData --fields="name:string,quantity:int"
php artisan make:action Orders CancelOrder --data=OrderData --transaction
php artisan make:crud Orders Order --fields="name:string,quantity:int" --api --web
```

To change generated code, publish the stubs (`php artisan vendor:publish --tag=blueprint-stubs`) and edit `stubs/blueprint/*.stub`.

## Non-negotiables (summary)

- Modular monolith under `app/Modules/` + `app/Shared/`.
- Controllers: HTTP in/out only; business logic in Actions/Services.
- Requests: validation + authorization; Data classes for typed transfer.
- Migrations: `database/migrations/` (central) and `database/migrations/tenant/` only — never inside modules.
- Tenancy: stancl/tenancy, subdomain ID, one DB per tenant, central DB is registry-only.
- Features/settings: `features` and `settings` tables in the tenant DB (tenant-saas) or the app DB (saas); platform admin toggles features through console commands; backend enforcement when disabled.
- Names: short, simple English ([NAMING.md](./NAMING.md)).

## Amendments

Conflicts with [rules.md](../rules.md) are resolved in favor of **approved Phase 0 specs** until `rules.md` is updated to match.
