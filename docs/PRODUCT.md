# Laravel Engineering Blueprint — product model

**Positioning:** A standardized engineering system for building maintainable Laravel applications with **humans and AI agents** — not “just another Laravel package.”

The repo delivers **three integrated products**:

```text
┌─────────────────────────────────────────────────────────────┐
│              Laravel Engineering Blueprint (LEB)             │
├─────────────────┬─────────────────────┬─────────────────────┤
│ 1. Engineering  │ 2. Laravel Toolkit  │ 3. AI Engineering   │
│    Standard     │    (runtime)        │    Kit              │
├─────────────────┼─────────────────────┼─────────────────────┤
│ docs/           │ Package + Artisan   │ kit/ skills, rules, │
│ PHASE-0 specs   │ generators, Shared  │ leb-install, agent  │
│ rules.md        │ features, tenancy   │ workflows           │
└─────────────────┴─────────────────────┴─────────────────────┘
```

## 1. Engineering Standard (foundation)

**What:** Approved architecture, naming, tenancy, features, settings, permissions, and backlog standards.

**Where:** [PHASE-0.md](./PHASE-0.md), linked specs, [STANDARDS-BACKLOG.md](./STANDARDS-BACKLOG.md), [DOCUMENT-HIERARCHY.md](./DOCUMENT-HIERARCHY.md).

**Rule:** Code, stubs, and agent instructions must not contradict the Standard without a documented amendment.

## 2. Laravel Toolkit (runtime)

**What:** `best/laravel-engineering-blueprint` — install profiles, `make:*` commands, Shared components, tenancy and feature commands.

**Where:** [../README.md](../README.md), `src/`, `stubs/`, `config/blueprint.php`.

**Rule:** Generators implement the Standard; gaps are tracked in [GENERATOR-ROADMAP.md](./GENERATOR-ROADMAP.md) and [SECURITY-GENERATION.md](./SECURITY-GENERATION.md).

## 3. AI Engineering Kit (differentiator)

**What:** Portable skills and rules so Cursor, Codex, Claude, Antigravity, Gemini, and others use the **same commands and specs**.

**Where:** `kit/`, `bin/leb-install`, [AI-WORKFLOW.md](./AI-WORKFLOW.md).

**Target workflow:** Agent reads architecture → picks module → applies rules → runs `make:*` → runs tests/static analysis → checks architecture → reports what it could not safely do.

## What belongs where

| Capability | Standard (doc) | Toolkit (code) | Generator only |
|------------|----------------|----------------|----------------|
| API response shape | [API-RESPONSES.md](./API-RESPONSES.md) | JsonResource (envelope planned) | resource stub |
| Pagination conventions | [API-RESPONSES.md](./API-RESPONSES.md) | `paginate(15)` in query stub | list endpoints |
| Idempotency | [IDEMPOTENCY.md](./IDEMPOTENCY.md) | optional `idempotent` middleware | config `idempotency.*` |
| Policy deny-by-default | [SECURITY-GENERATION.md](./SECURITY-GENERATION.md) | **shipped** `policy.stub` | policy templates |
| Schema DSL (FK, enum, indexes, field rules) | [SCHEMA-DSL.md](./SCHEMA-DSL.md) | **shipped** `EntitySchemaLoader` | migration/model/requests |
| Module boundaries | [MODULE-BOUNDARIES.md](./MODULE-BOUNDARIES.md) | PHPStan rules + `engineering:architecture` | — |

Not everything is a CRUD flag. Prefer **reusable framework pieces** + **rules** + **optional generators** ([ROADMAP.md](./ROADMAP.md)).

## Critical domains (3PL / SaaS)

Validate **independently** before trusting generated code alone:

- Tenancy isolation and jobs
- Authorization and permissions
- Inventory transactions and concurrency
- Idempotency on financial or stock mutations

Use generated CRUD as a starting point; use Actions, transactions, and tests for critical paths.
