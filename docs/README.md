# Documentation index

## Product

| Doc | Purpose |
|-----|---------|
| [PRODUCT.md](./PRODUCT.md) | Three pillars: Standard, Toolkit, AI Kit |
| [ENGINEERING-AUDIT.md](./ENGINEERING-AUDIT.md) | Repository audit findings and status |
| [DOCUMENT-HIERARCHY.md](./DOCUMENT-HIERARCHY.md) | Single source of truth order |
| [ROADMAP.md](./ROADMAP.md) | Strategic tracks (constitution, generator, enforcement) |
| [HARDENING.md](./HARDENING.md) | **Current phase:** verification, MySQL CI, dependency graph, stub matrix |

## Phase 0 — Architecture Foundation (approved)

| Doc | Purpose |
|-----|---------|
| [PHASE-0.md](./PHASE-0.md) | Status, acceptance criteria |
| [ARCHITECTURE.md](./ARCHITECTURE.md) | Structure, classes, modules, frontend, generated code |
| [VALIDATION.md](./VALIDATION.md) | Form Request and validation standard |
| [DTO.md](./DTO.md) | Data / DTO standard |
| [ACTIONS.md](./ACTIONS.md) | Actions and services |
| [API-RESPONSES.md](./API-RESPONSES.md) | API JSON and status codes |
| [IDEMPOTENCY.md](./IDEMPOTENCY.md) | Duplicate submission and idempotency layers |
| [TESTING.md](./TESTING.md) | Pest and generated-test conventions |
| [MODULE-BOUNDARIES.md](./MODULE-BOUNDARIES.md) | Domain boundaries, cross-module rules |
| [NAMING.md](./NAMING.md) | Simple English naming |
| [TENANCY.md](./TENANCY.md) | stancl/tenancy, central vs tenant |
| [TENANCY-EXTENSIBILITY.md](./TENANCY-EXTENSIBILITY.md) | Strategies beyond DB-per-tenant |
| [FEATURES.md](./FEATURES.md) | Feature ON/OFF |
| [SETTINGS.md](./SETTINGS.md) | Tenant/app settings |
| [PERMISSIONS.md](./PERMISSIONS.md) | Roles and permissions |
| [CONSTITUTION.md](./CONSTITUTION.md) | Agent entry point |

## Generator, security, quality

| Doc | Purpose |
|-----|---------|
| [SCHEMA-DSL.md](./SCHEMA-DSL.md) | `make:crud --schema=` YAML format |
| [SCHEMA-BACKLOG.md](./SCHEMA-BACKLOG.md) | Schema DSL shipped vs planned |
| [GENERATOR-ROADMAP.md](./GENERATOR-ROADMAP.md) | Schema backlog, relationships, profiles |
| [SECURITY-GENERATION.md](./SECURITY-GENERATION.md) | Policy defaults for generated CRUD |
| [AUTHENTICATION.md](./AUTHENTICATION.md) | Route auth: package opt-in vs `bin/new-app` secure bootstrap |
| [PRODUCTION-DEFAULTS.md](./PRODUCTION-DEFAULTS.md) | Greenfield checklist (auth, permissions, idempotency) |
| [STANDARDS-BACKLOG.md](./STANDARDS-BACKLOG.md) | API envelope, pagination, idempotency, … |
| [QUALITY-ROADMAP.md](./QUALITY-ROADMAP.md) | CI, tenancy tests, upgrade tests |
| [AI-WORKFLOW.md](./AI-WORKFLOW.md) | Target agent loop |

**Narrative:** [../rules.md](../rules.md)

**Package usage:** [../README.md](../README.md)

Internal build history: `docs/superpowers/` (not copied by `leb-install`).
