# Tenancy extensibility

## Current default (tenant-saas profile)

- **Package:** [stancl/tenancy](https://tenancyforlaravel.com/)
- **Strategy:** **Database per tenant**
- **Identification:** Subdomain (see [TENANCY.md](./TENANCY.md))

This is a **legitimate, opinionated choice** for strong isolation — not a claim to be the only valid SaaS tenancy model.

## Strategies we may support later

| Strategy | Isolation | Ops complexity | Typical use |
|----------|-----------|----------------|-------------|
| Shared DB + `tenant_id` | Row-level | Lower | Many small tenants |
| Database per tenant | Connection-level | Higher | Strong isolation, compliance |
| Hybrid | Mixed | Highest | Enterprise tiers |

**We will not implement all three immediately.** Phase 1 goal: avoid baking “database per tenant” into every abstraction so switching (or mixing) strategies does not require rewriting modules.

## Design rules (now)

1. **Tenant context** — all tenant-scoped code runs only after tenancy is initialized (middleware / `Tenant::run()`).
2. **No browser-trusted tenant id** — resolve tenant from domain / package identification.
3. **Central vs tenant data** — per [TENANCY.md](./TENANCY.md); features/settings location follows profile (`saas` vs `tenant-saas`).
4. **Module code** — must not assume a global `tenant_id` column unless profile/document explicitly adopts shared-database strategy.
5. **Jobs and schedules** — must re-establish tenant context; test switching ([QUALITY-ROADMAP.md](./QUALITY-ROADMAP.md)).

## Extension points (target)

- `config/blueprint.php` tenancy strategy enum (future).
- Bootstrapper list swappable per strategy.
- Migration runner interface (central vs tenant paths already separated).

Custom domains can be added without changing the per-tenant database design ([TENANCY.md](./TENANCY.md) §3.4).
