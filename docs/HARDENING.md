# Hardening & verification phase

External engineering review (~**8.5/10**, 2026-11) concluded the product direction is strong; the next phase is **not** a feature dump. It is **proving** that generated output is safe, consistent, and behaves under production-like conditions.

## Platform vs conventions

**Phase 0 + specs** are the constitution; **generators + package code** are the platform (commands, stubs, tenancy, permissions sync, idempotency middleware). **Hardening** does not add product surface — it adds **evidence**: CI jobs, Pest regressions, e2e profiles, kit grep tests, and checklists so drift is caught before release.

| Track | Proof mechanism | Status |
|-------|-----------------|--------|
| Production reliability | MySQL CI job (`mysql-integration`), `tests/MysqlIntegration/` | **Shipped** — CI fails if MySQL unreachable when `LEB_MYSQL=1` |
| Architecture 9/10 | `ModuleDependencyGraphTest` + `engineering:architecture` | **Shipped** |
| CRUD / stubs | Snapshots, `composer check` L12/L13, `bin/e2e` profiles | **Partial** — matrix below |
| Idempotency | Feature + MySQL cache tests, checklist, `composer test:stress` (pcntl overlap) in CI matrix | **Shipped** |
| AI kit | `KitAuthParityTest`, `KitGeneratorParityTest` | **Shipped** (extend when stubs change) |

**Release gate:** Treat green CI on the **default branch** as mandatory before calling a release production-ready. Matrix jobs can fail independently — verify Actions on the branch you ship ([QUALITY-ROADMAP.md](./QUALITY-ROADMAP.md)).

## Domain scores (review snapshot)

| Area | Score | Hardening focus |
|------|-------|-----------------|
| Architecture & Standards | 9/10 | Keep hierarchy + amendment process; dependency graph tests |
| CRUD & Schema Generator | 8.5/10 | Stub matrix L12/L13; snapshots; schema edge cases |
| Security & Permissions | 8.5/10 | Auth model docs; generated tests vs sanctum/web auth |
| Multi-tenancy | 8/10 | MySQL tenant DB paths; jobs/schedule scenarios |
| Testing & CI design | 8.5/10 | MySQL job; prove locks/transactions differ from SQLite |
| Production reliability | 7/10 | Idempotency + real cache; DB integration |
| AI Engineering Kit | 8.5/10 | Skills/rules match stubs and [AUTHENTICATION.md](./AUTHENTICATION.md) |

Scores reflect **design and reviewed code**, not a certificate that every workflow was green at review time.

---

## Six workstreams (ordered)

### 1. Idempotency — HTTP layer

| | |
|---|---|
| **Goal** | Same key + same payload replays; different body/query → **409**; concurrent keys → one execution. |
| **Status** | **Shipped:** `ReplayIdempotentRequests`, fingerprint, `Cache::lock`, Pest coverage ([IDEMPOTENCY.md](./IDEMPOTENCY.md)). |
| **Still to prove** | Optional k6 load; **DB unique** `(tenant_id, idempotency_key)` left to apps (documented in checklist). |
| **Acceptance** | Documented production checklist + optional integration test group that fails if lock/replay regresses. |

### 2. MySQL (or PostgreSQL) integration tests

| | |
|---|---|
| **Goal** | Behavior that SQLite hides: transactions, indexes, tenancy `CREATE DATABASE`, migration ordering. |
| **Status** | **Partial** — CI job `mysql-integration` runs `tests/MysqlIntegration/` ([TESTING.md](./TESTING.md)); local opt-in via `composer test:mysql`. |
| **Acceptance** | Separate CI job (service container); focused Pest path; does not run inside `composer check` (SQLite matrix stays fast). |
| **First scenarios** | Tenant create/migrate; permission sync; FK/index; schema DSL migrate; tenant queue; **`tenants:run`** (`TenancyScheduleMysqlTest`). |

### 3. Pagination standards

| | |
|---|---|
| **Goal** | Configurable default page size and **hard cap** on `per_page`. |
| **Status** | **Mostly shipped:** `ListPerPage`, `blueprint.pagination.per_page` / `max_per_page`, generated index + `?per_page=` ([API-RESPONSES.md](./API-RESPONSES.md)). |
| **Still open** | Cursor pagination; shared list-Query base; document max in public API contract per app. |
| **Acceptance** | STANDARDS-BACKLOG pagination row → “shipped (offset)” + cursor explicitly backlog. |

### 4. Module dependency graph (Pest)

| | |
|---|---|
| **Goal** | Fail CI when module A imports forbidden module B (complement PHPStan + `engineering:architecture`). |
| **Status** | **Shipped:** `ModuleDependencyGraphTest` + [DEPENDENCY-GRAPH.md](../tests/Fixtures/module-boundaries/DEPENDENCY-GRAPH.md) fixture. |
| **Acceptance** | Golden fixture; scanner + `engineering:architecture` CLI; PHPStan for non-`use` references (existing). |

### 5. Generated stubs — Laravel 12 & 13 matrix

| | |
|---|---|
| **Goal** | Every profile × auth mode × permissions × envelope produces valid, tested apps. |
| **Status** | **Partial:** `composer check` matrix L12/L13; `bin/e2e` all profiles; CRUD snapshots; not every combinatorial flag. |
| **Acceptance** | Checklist matrix in this doc (below) all green in CI; `UPDATE_CRUD_SNAPSHOTS=1` policy documented in [TESTING.md](./TESTING.md). |
| **Combinations to cover** | `--permissions`, `--owned`, `--auth=sanctum`, `route_auth_web`, `--api-envelope`, tenant-saas, `--central`. |

### 6. AI Kit ↔ code parity

| | |
|---|---|
| **Goal** | Agent instructions never contradict stubs or config defaults. |
| **Status** | **Ongoing** — auth model synced in [AUTHENTICATION.md](./AUTHENTICATION.md) + `kit/skills/`. |
| **Acceptance** | When generator or config changes: same PR updates `stubs/`, `kit/skills/`, `kit/rules`, and linked spec; `tests/Unit/Kit/KitAuthParityTest.php` greps for stale auth phrases. |
| **Process** | [DOCUMENT-HIERARCHY.md](./DOCUMENT-HIERARCHY.md) amendment + `AGENTS.md` / `leb-install` smoke. |

---

## Stub verification matrix (track 5)

Mark in PRs when a row is re-verified after stub changes.

| Profile | API auth | Web auth | Policy | Envelope | Verified by |
|---------|----------|----------|--------|----------|-------------|
| modular | config null | config null | deny / open / permissions | off | snapshots + MakeCrud* tests |
| modular | sanctum | auth | permissions + owned | off | `bin/e2e` **modular** (`--permissions --owned`); `bin/new-app` |
| saas | sanctum | auth | permissions + feature + owned | optional | `bin/e2e` **saas**; feature enable/disable in e2e |
| tenant-saas | sanctum | auth | permissions tenant + central | optional | `bin/e2e` **tenant-saas**; PermissionsTenancy* |
| modular | sanctum | — | permissions + owned | on | `StubMatrixEnvelopeSnapshotTest` + `bin/e2e` Billing/Invoice |

---

## What we are **not** doing in this phase

- New install profiles or large new generators ([ROADMAP.md](./ROADMAP.md)).
- Feature parity with every SaaS boilerplate.
- Changing package `route_auth` defaults to `sanctum` globally (breaks existing apps); greenfield stays **`bin/new-app`** ([AUTHENTICATION.md](./AUTHENTICATION.md)).

---

## Links

- Living gap list: [ENGINEERING-AUDIT.md](./ENGINEERING-AUDIT.md)
- CI expansion: [QUALITY-ROADMAP.md](./QUALITY-ROADMAP.md)
- Runtime standards backlog: [STANDARDS-BACKLOG.md](./STANDARDS-BACKLOG.md)
