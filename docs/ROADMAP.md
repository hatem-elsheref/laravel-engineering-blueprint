# Roadmap — next priorities

External review (2026) scored direction ~8–8.5/10. **Current phase:** [HARDENING.md](./HARDENING.md) — verification and production-like CI, not a feature expansion.

**Do not** add many new profiles or generators until hardening workstreams 2–4 and stub matrix (5) advance:

## 1. Engineering Constitution (in progress)

**Goal:** One unambiguous source of truth — no conflicting rules between docs, stubs, and agents.

**Actions:**

- [x] [DOCUMENT-HIERARCHY.md](./DOCUMENT-HIERARCHY.md)
- [x] [PRODUCT.md](./PRODUCT.md) — three-pillar model
- [x] Module and tenancy extension specs
- [x] Align [rules.md](../rules.md) with specs where still divergent
- [ ] Versioned “amendments” section in PHASE-0 when stack decisions change

## 2. Generator completeness

**Goal:** Production-oriented CRUD and schema, not demo fields only.

**Actions:**

- [x] Schema definition format v1 ([SCHEMA-DSL.md](./SCHEMA-DSL.md), [GENERATOR-ROADMAP.md](./GENERATOR-ROADMAP.md))
- [x] Deny-by-default policies + `--open-policy` ([SECURITY-GENERATION.md](./SECURITY-GENERATION.md))
- [x] Relationships, enums, decimals, indexes in schema (v1; scopes and nested resources later)
- [x] List/query: filters in schema ([SCHEMA-DSL.md](./SCHEMA-DSL.md))
- [x] Pagination: `ListPerPage` + `blueprint.pagination.*` ([API-RESPONSES.md](./API-RESPONSES.md))
- [ ] Cursor pagination / shared list Query base ([STANDARDS-BACKLOG.md](./STANDARDS-BACKLOG.md))

## 3. Architecture enforcement

**Goal:** Verify code follows the Standard — not only read instructions.

**Actions:**

- [x] `engineering:architecture` — cross-module import scanner ([MODULE-BOUNDARIES.md](./MODULE-BOUNDARIES.md))
- [x] PHPStan module-boundary rules (`phpstan/blueprint-module-boundaries.neon`; deeper than `use` lines)
- [x] Pest dependency graph tests ([DEPENDENCY-GRAPH.md](../tests/Fixtures/module-boundaries/DEPENDENCY-GRAPH.md), `ModuleDependencyGraphTest`)
- [x] Agent workflow documents architecture command ([AI-WORKFLOW.md](./AI-WORKFLOW.md))
- [ ] Tenancy security & generated-code regression tests ([QUALITY-ROADMAP.md](./QUALITY-ROADMAP.md))

## Quality bar (existing + planned)

Already in repo: PHPStan 6, Pint, Pest, CI matrix (PHP 8.3/8.4, Laravel 12/13).

Planned: upgrade tests from prior package versions, production-like DB configs, stub compatibility tests — see [QUALITY-ROADMAP.md](./QUALITY-ROADMAP.md).

**Note:** CI presence does not guarantee every matrix cell is green; check GitHub Actions on the default branch before release.
