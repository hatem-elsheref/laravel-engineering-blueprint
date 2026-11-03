# Document hierarchy — single source of truth

Conflicts between `rules.md`, stubs, generated output, and agent files are resolved using this order (**highest wins first**).

| Priority | Source | Role |
|----------|--------|------|
| 1 | [PHASE-0.md](./PHASE-0.md) | Approved scope and stack |
| 2 | Phase 0 specs | [ARCHITECTURE.md](./ARCHITECTURE.md), [NAMING.md](./NAMING.md), [TENANCY.md](./TENANCY.md), [FEATURES.md](./FEATURES.md), [SETTINGS.md](./SETTINGS.md), [PERMISSIONS.md](./PERMISSIONS.md) |
| 3 | Extension specs | [MODULE-BOUNDARIES.md](./MODULE-BOUNDARIES.md), [TENANCY-EXTENSIBILITY.md](./TENANCY-EXTENSIBILITY.md), [SECURITY-GENERATION.md](./SECURITY-GENERATION.md), [VALIDATION.md](./VALIDATION.md), [DTO.md](./DTO.md), [ACTIONS.md](./ACTIONS.md), [API-RESPONSES.md](./API-RESPONSES.md), [IDEMPOTENCY.md](./IDEMPOTENCY.md), [TESTING.md](./TESTING.md), [STANDARDS-BACKLOG.md](./STANDARDS-BACKLOG.md) |
| 3b | Audit (living) | [ENGINEERING-AUDIT.md](./ENGINEERING-AUDIT.md) — gaps and remediation; amend when closing findings |
| 4 | Roadmaps | [ROADMAP.md](./ROADMAP.md), [GENERATOR-ROADMAP.md](./GENERATOR-ROADMAP.md), [QUALITY-ROADMAP.md](./QUALITY-ROADMAP.md), [AI-WORKFLOW.md](./AI-WORKFLOW.md) |
| 5 | [rules.md](../rules.md) | Narrative and history — update when specs change |
| 6 | [README.md](../README.md) | Operator guide — must match specs |
| 7 | `stubs/` + `kit/` | Implementation of Standard — amend stubs when behavior changes |
| 8 | Generated app code | Never the authority; regenerate or hand-fix toward specs |

## Amendment process

1. Describe the change and why in a short amendment block at the bottom of the affected spec (or new spec under `docs/`).
2. Update [CONSTITUTION.md](./CONSTITUTION.md) and agent templates if agents must behave differently.
3. Update `stubs/` and tests in the same change when generator output changes.
4. Sync [rules.md](../rules.md) when the narrative should match (optional but recommended).

## Agent instruction

When instructions conflict, follow this hierarchy. If `rules.md` disagrees with Phase 0 specs, **specs win** until `rules.md` is updated.
