# Laravel Engineering Standard

## Phase 0 — Architecture Foundation

**Status:** Draft for approval  
**Architecture:** Modular Monolith  
**DTO:** Native PHP  
**Generator:** Advanced Artisan Generator  
**Frontend:** Blade + Livewire + Vue  
**Multi-tenancy:** stancl/tenancy  
**Database:** Central database + separate database per tenant  

Phase 0 fixes the engineering constitution. No features implementation, no CRUD generation, no application classes until these specifications are approved.

Any new decision must match this blueprint or be documented as an amendment here or in the linked spec files.

## Specifications

| Document | Purpose |
|----------|---------|
| [ARCHITECTURE.md](./ARCHITECTURE.md) | Project shape and responsibilities |
| [NAMING.md](./NAMING.md) | Simple naming rules |
| [TENANCY.md](./TENANCY.md) | Central vs tenant, stancl/tenancy |
| [FEATURES.md](./FEATURES.md) | Feature ON/OFF |
| [SETTINGS.md](./SETTINGS.md) | Tenant settings |

Extended narrative (Arabic/English): [../rules.md](../rules.md).

## Phase 0 acceptance criteria

Phase 0 is complete when:

- [ ] Architecture is approved.
- [ ] Naming rules are approved.
- [ ] Central and tenant database responsibilities are clear.
- [ ] stancl/tenancy integration approach is approved.
- [ ] Subdomain identification is defined.
- [ ] Tenant creation lifecycle is defined.
- [ ] Central and tenant migration paths are defined.
- [ ] Feature ownership and permissions are defined.
- [ ] Settings storage is defined.
- [ ] Feature cache invalidation behavior is defined.
- [ ] No unnecessary architecture layers are required.

**Next phase:** Phase 1 — Laravel Foundation and Tenancy Setup.
