# Roadmap — next priorities

External review (2026) scored direction ~8/10; main gaps: generator depth, security defaults, enforceability, tenancy extensibility narrative.

**Do not** add many new profiles or generators until these three tracks advance:

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
- [ ] List/query: filters, pagination standards ([STANDARDS-BACKLOG.md](./STANDARDS-BACKLOG.md))

## 3. Architecture enforcement

**Goal:** Verify code follows the Standard — not only read instructions.

**Actions:**

- [x] `engineering:architecture` — cross-module import scanner ([MODULE-BOUNDARIES.md](./MODULE-BOUNDARIES.md))
- [x] PHPStan module-boundary rules (`phpstan/blueprint-module-boundaries.neon`; deeper than `use` lines)
- [ ] Pest dependency graph tests
- [x] Agent workflow documents architecture command ([AI-WORKFLOW.md](./AI-WORKFLOW.md))
- [ ] Tenancy security & generated-code regression tests ([QUALITY-ROADMAP.md](./QUALITY-ROADMAP.md))

## Quality bar (existing + planned)

Already in repo: PHPStan 6, Pint, Pest, CI matrix (PHP 8.3/8.4, Laravel 12/13).

Planned: upgrade tests from prior package versions, production-like DB configs, stub compatibility tests — see [QUALITY-ROADMAP.md](./QUALITY-ROADMAP.md).

**Note:** CI presence does not guarantee every matrix cell is green; check GitHub Actions on the default branch before release.
