# Engineering constitution (agents)

**Authority:** [Phase 0 — Architecture Foundation](./PHASE-0.md). These specs are the project constitution. Code and generators must follow them unless an amendment is recorded in Phase 0 docs.

## Read in order

1. [ARCHITECTURE.md](./ARCHITECTURE.md)
2. [NAMING.md](./NAMING.md)
3. [TENANCY.md](./TENANCY.md)
4. [FEATURES.md](./FEATURES.md)
5. [SETTINGS.md](./SETTINGS.md)

Long-form discussion: [../rules.md](../rules.md).

## Phase 0 scope

Do **not** implement features, settings services, modules, or CRUD until Phase 0 acceptance criteria in [PHASE-0.md](./PHASE-0.md) are approved.

## Non-negotiables (summary)

- Modular monolith under `app/Modules/` + `app/Shared/`.
- Controllers: HTTP in/out only; business logic in Actions/Services.
- Requests: validation + authorization; Data classes for typed transfer.
- Migrations: `database/migrations/` (central) and `database/migrations/tenant/` only — never inside modules.
- Tenancy: stancl/tenancy, subdomain ID, one DB per tenant, central DB is registry-only.
- Features/settings: tenant DB tables; platform admin toggles features; backend enforcement when disabled.
- Names: short, simple English ([NAMING.md](./NAMING.md)).

## Amendments

Conflicts with [rules.md](../rules.md) are resolved in favor of **approved Phase 0 specs** until `rules.md` is updated to match.
