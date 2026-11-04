# Engineering constitution (agents)

**Authority:** [DOCUMENT-HIERARCHY.md](./DOCUMENT-HIERARCHY.md) — Phase 0 specs win over narrative and stubs when they conflict.

## Read in order

1. [PHASE-0.md](./PHASE-0.md)
2. [ARCHITECTURE.md](./ARCHITECTURE.md) + [MODULE-BOUNDARIES.md](./MODULE-BOUNDARIES.md)
3. [NAMING.md](./NAMING.md)
4. [TENANCY.md](./TENANCY.md) + [TENANCY-EXTENSIBILITY.md](./TENANCY-EXTENSIBILITY.md)
5. [FEATURES.md](./FEATURES.md) · [SETTINGS.md](./SETTINGS.md) · [PERMISSIONS.md](./PERMISSIONS.md)
6. [SECURITY-GENERATION.md](./SECURITY-GENERATION.md) when generating CRUD
7. [VALIDATION.md](./VALIDATION.md) · [DTO.md](./DTO.md) · [ACTIONS.md](./ACTIONS.md) · [API-RESPONSES.md](./API-RESPONSES.md) · [TESTING.md](./TESTING.md) · [IDEMPOTENCY.md](./IDEMPOTENCY.md)
8. [AI-WORKFLOW.md](./AI-WORKFLOW.md) for end-to-end agent tasks

Product model: [PRODUCT.md](./PRODUCT.md). Audit: [ENGINEERING-AUDIT.md](./ENGINEERING-AUDIT.md). Priorities: [ROADMAP.md](./ROADMAP.md).

Long-form discussion: [../rules.md](../rules.md).

## Phase 0 status

Phase 0 is **approved**. Package `best/laravel-engineering-blueprint` implements it. Ongoing work is tracked in roadmaps (generator, enforcement, quality) — not new architecture phases unless amended in PHASE-0.

## Generate code with the commands

Use Artisan; do not hand-copy stubs ([../README.md](../README.md)):

```bash
php artisan make:module Orders --api --web
php artisan make:dto Orders OrderData --fields="name:string,quantity:int"
php artisan make:action Orders CancelOrder --data=OrderData --transaction
php artisan make:service Orders OrderPricing --data=OrderData
php artisan make:crud Orders Order --fields="name:string,quantity:int" --api --web --permissions
php artisan make:crud Orders Order --schema=orders/order.yaml --api   # enums, FKs, indexes — SCHEMA-DSL.md
php artisan make:crud Orders Order --api --auth=sanctum --permissions   # or set blueprint.route_auth in config
php artisan make:crud Orders Order --api --api-envelope   # optional { data, meta } JSON envelope
# Demo only: add --open-policy when not using --permissions
```

After module work: `php artisan engineering:architecture` ([MODULE-BOUNDARIES.md](./MODULE-BOUNDARIES.md)).

Production CRUD: prefer **`--permissions`** (and **`--owned`** when rows belong to users). Route auth is opt-in on `make:crud`; greenfield: **`bin/new-app`** without **`--demo`**. See [AUTHENTICATION.md](./AUTHENTICATION.md) and [SECURITY-GENERATION.md](./SECURITY-GENERATION.md).

Publish stubs: `php artisan vendor:publish --tag=blueprint-stubs`.

## Non-negotiables (summary)

- Modular monolith: `app/Modules/` + `app/Shared/`; cross-module via contracts, public actions, events ([MODULE-BOUNDARIES.md](./MODULE-BOUNDARIES.md)).
- Controllers: HTTP only; business logic in Actions/Services.
- Requests + Data for input; Queries for list/read patterns.
- Migrations: global paths only — never inside modules.
- Tenancy: default profile is DB-per-tenant; not the only strategy long-term ([TENANCY-EXTENSIBILITY.md](./TENANCY-EXTENSIBILITY.md)).
- Features/settings: per profile; backend enforcement when disabled.
- Names: [NAMING.md](./NAMING.md).

## Amendments

Record changes in the relevant spec, then sync stubs/tests/README per [DOCUMENT-HIERARCHY.md](./DOCUMENT-HIERARCHY.md).
