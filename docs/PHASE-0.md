# Laravel Engineering Standard

## Phase 0 — Architecture Foundation

**Status:** Approved  
**Architecture:** Modular Monolith  
**DTO:** Native PHP  
**Generator:** Advanced Artisan Generator  
**Frontend:** Blade + Livewire + Vue  
**Multi-tenancy:** stancl/tenancy  
**Database:** Central database + separate database per tenant  

Phase 0 fixes the engineering constitution. It is approved; the package (`best/laravel-engineering-blueprint`) implements it. All phases (0–10) are done; 1.0.0 is prepared (see RELEASING.md).

Any new decision must match this blueprint or be documented as an amendment here or in the linked spec files.

## Specifications

| Document | Purpose |
|----------|---------|
| [ARCHITECTURE.md](./ARCHITECTURE.md) | Project shape and responsibilities |
| [NAMING.md](./NAMING.md) | Simple naming rules |
| [TENANCY.md](./TENANCY.md) | Central vs tenant, stancl/tenancy |
| [FEATURES.md](./FEATURES.md) | Feature ON/OFF |
| [SETTINGS.md](./SETTINGS.md) | Tenant settings |
| [PERMISSIONS.md](./PERMISSIONS.md) | Roles, permissions, ownership |

Package usage (install, commands, config): [../README.md](../README.md).

Extended narrative (Arabic/English): [../rules.md](../rules.md).

## Phase 0 acceptance criteria

Phase 0 is complete (all criteria met):

- [x] Architecture is approved.
- [x] Naming rules are approved.
- [x] Central and tenant database responsibilities are clear.
- [x] stancl/tenancy integration approach is approved.
- [x] Subdomain identification is defined.
- [x] Tenant creation lifecycle is defined.
- [x] Central and tenant migration paths are defined.
- [x] Feature ownership and permissions are defined.
- [x] Settings storage is defined.
- [x] Feature cache invalidation behavior is defined.
- [x] No unnecessary architecture layers are required.

All phases (0–10) are done; 1.0.0 is prepared (see RELEASING.md). Permissions are specified in [PERMISSIONS.md](./PERMISSIONS.md).
