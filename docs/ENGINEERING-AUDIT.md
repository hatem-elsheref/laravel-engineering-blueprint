# Engineering audit — Laravel Engineering Blueprint

**Last audit:** 2026-11-04  
**Scope:** Full repository inspection (package, generators, stubs, tests, CI, kit, docs).  
**Method:** Code and test review; not documentation-only.

Findings are classified **Critical / High / Medium / Low**. Status tags: **Open**, **Partial**, **Addressed** (with pointer).

Authority for fixes: [DOCUMENT-HIERARCHY.md](./DOCUMENT-HIERARCHY.md). This audit does not override Phase 0 specs; it tracks gaps and remediation.

---

## Executive summary

LEB delivers a **coherent modular monolith toolkit** with deny-default policies, schema-driven CRUD (v1–v2), module boundary scanning, PHPStan boundary rules for apps, multi-profile install, and strong Pest coverage (~730+ tests). The three product pillars (Standard, Toolkit, AI Kit) are real, not marketing-only.

**Main risks for production apps:**

1. Generated routes **do not include authentication middleware** — authorization is policy/FormRequest-only until developers add `auth` / `auth:sanctum`.
2. Default **`make:crud` policies deny all abilities** — correct for security, but teams must implement policy, use `--permissions`, or (demo only) `--open-policy`.
3. **Runtime standards** — API envelope helper shipped (opt-in); idempotency HTTP layer shipped; pagination config shipped; MySQL CI and dependency-graph tests remain backlog.
4. **Cross-tenant CRUD regression** was under-tested (addressed in this audit cycle — see tests).

---

## Critical

| ID | Finding | Evidence | Status |
|----|---------|----------|--------|
| C1 | API/web CRUD routes ship without auth middleware | Default unchanged; `blueprint.route_auth` + `make:crud --auth=` | **Partial** — opt-in/config; web routes still manual |
| C2 | Default CRUD unusable until policy/permissions configured | `stubs/policy.stub` (deny all); `test-deny.stub` | **Open** — by design; mitigated by docs and `--permissions` |
| C3 | Cross-tenant read by ID on shared auto-increment keys | Separate DB per tenant; logical isolation | **Addressed** — `TenantCrudEndToEndTest` cross-tenant case |

---

## High

| ID | Finding | Evidence | Status |
|----|---------|----------|--------|
| H1 | `stubs/README.md` misdescribed default policy as “signed-in users allowed” | vs `policy.stub` | **Addressed** |
| H2 | `docs/PRODUCT.md` matrix listed deny-default policy and schema parser as “planned” | Shipped in `src/` | **Addressed** |
| H3 | No first-class specs for validation, DTO, actions, API responses, idempotency, testing | Only `ARCHITECTURE.md` §1.3 + `rules.md` narrative | **Addressed** — new `docs/*` standards (see below) |
| H4 | `README.md` still listed deny-default as “next priority” | vs shipped behavior | **Addressed** |
| H5 | Kit skill claimed “all phases done” without matching open roadmap items | `kit/skills/laravel-engineering/SKILL.md` | **Addressed** |
| H6 | `SCHEMA-DSL.md` backlog footer stale (field rules shipped v2) | [SCHEMA-BACKLOG.md](./SCHEMA-BACKLOG.md) | **Addressed** |
| H7 | E2E runs Laravel ^13 only; CI matrix includes Laravel 12 | `bin/e2e`, `.github/workflows/ci.yml` | **Addressed** — `e2e-laravel-12` CI job runs `bin/e2e --laravel=^12.0` |
| H8 | Package `composer check` PHPStan does not analyze consumer boundary fixtures | `phpstan.neon` paths `src/` | **Open** — fixture tests cover rules |

---

## Medium

| ID | Finding | Evidence | Status |
|----|---------|----------|--------|
| M1 | API uses Laravel default JsonResource shape, not custom envelope | `ApiResponse`, `--api-envelope` | **Partial** — opt-in envelope; default remains JsonResource |
| M2 | List queries hard-code `paginate(15)` | `ListPerPage` + `blueprint.pagination.*` | **Partial** — config + `?per_page=`; cursor style still open |
| M3 | `engineering:architecture` unavailable on `basic` profile | `Modules::profileError()` | **Open** — document in README |
| M4 | `rules.md` references `TESTING_RULES.md` (missing file) | grep | **Partial** — [TESTING.md](./TESTING.md) replaces for agents |
| M5 | E2E modular profile uses `--permissions --owned`, not default deny path | `bin/e2e` | **Open** — intentional happy path |
| M6 | QUALITY-ROADMAP says PHPStan covers `use` imports | CLI `engineering:architecture` does; PHPStan neon does not | **Open** — clarify in MODULE-BOUNDARIES |

---

## Low

| ID | Finding | Evidence | Status |
|----|---------|----------|--------|
| L1 | PHASE-0 has no Artisan command catalog | Commands in README | **Open** |
| L2 | Livewire/Vue in ARCHITECTURE not generated | Phase 0 frontend choice | **Open** — expected |
| L3 | `kit/tools/README.md` placeholder | — | **Open** |
| L4 | Narrative `rules.md` very long; historical Arabic sections | Header points to specs | **Open** |

---

## Verified strengths

| Area | Evidence |
|------|----------|
| Command surface | `BlueprintServiceProvider` registers install, module, dto, action, crud, tenancy, features, settings, permissions, architecture |
| CRUD security modes | Default deny; `--open-policy`; `--permissions`; `--owned` with 404 vs 403 |
| Schema DSL | v1–v3: enums, FKs, indexes, scopes, field rules, casts, defaults, `hasMany` relations |
| Module boundaries | `ModuleBoundaryScanner`, `ModuleBoundaryPolicy`, PHPStan rules `blueprint.moduleBoundary` |
| Tenancy | `TenantSetup`, isolation tests, tenant CRUD e2e, permissions tenancy suite |
| CI | PHP 8.3/8.4 × Laravel 12/13 `composer check`; `bin/e2e` all profiles |
| AI kit | `leb-install`, skills, cursor rules, `AI-WORKFLOW.md`, `CONSTITUTION.md` |

---

## Standard documents (normative runtime)

Added in this audit cycle — short specs aligned with **current** generator output:

| Document | Purpose |
|----------|---------|
| [VALIDATION.md](./VALIDATION.md) | Form Request ownership, Laravel validation features, business vs input rules |
| [DTO.md](./DTO.md) | Data classes, `fromRequest`, immutability |
| [ACTIONS.md](./ACTIONS.md) | One operation per Action, transactions |
| [API-RESPONSES.md](./API-RESPONSES.md) | Current JsonResource behavior + planned envelope |
| [IDEMPOTENCY.md](./IDEMPOTENCY.md) | UI vs HTTP vs DB vs business idempotency |
| [TESTING.md](./TESTING.md) | Pest conventions for generated and hand-written code |

Backlog items that require **new Shared code** remain in [STANDARDS-BACKLOG.md](./STANDARDS-BACKLOG.md).

---

## Open priorities (review 2026-11)

| Priority | Item | Status |
|----------|------|--------|
| High | Idempotency: 409 + cache lock + query fingerprint | **Done** — pair with DB uniqueness for money/stock; **redis** (or lock-capable cache) in production |
| High | Real **MySQL/PostgreSQL** in CI | **Shipped (core)** — `mysql-integration` job; fails CI if MySQL unreachable; `tests/MysqlIntegration/` |
| Medium | Pagination | **Partial** — `ListPerPage`, config, `?per_page=` on generated API index |
| Medium | API envelope | **Opt-in shipped** — project must choose default vs envelope ([API-RESPONSES.md](./API-RESPONSES.md)) |
| Medium | Authentication | **Documented model** — [AUTHENTICATION.md](./AUTHENTICATION.md): opt-in `make:crud`; `bin/new-app` / `engineering:install --secure` for greenfield |
| Medium | Module **dependency graph** Pest tests | **Done** — `ModuleDependencyGraphTest` + fixture graph |
| Medium | Tenant **jobs / schedule** | **Partial** — CRUD + queue connection doc; deeper multi-job tests open ([QUALITY-ROADMAP.md](./QUALITY-ROADMAP.md)) |

## Recommended next steps (priority order)

Full hardening plan (six workstreams, acceptance criteria): **[HARDENING.md](./HARDENING.md)**.

1. **MySQL (or PostgreSQL) job in CI** for migrations, tenancy, and idempotency locks where SQLite differs.
2. **tenants:run** / schedule on MySQL (queue path covered in `TenancyQueueMysqlTest`).
3. **Stub matrix** combinatorial gaps ([HARDENING.md](./HARDENING.md) §5).

**Closed in §24 follow-up:** optional idempotency middleware; `route_auth_web` / web CRUD auth; schema v4 (filters, hidden/resource shaping, profiles); [PRODUCTION-DEFAULTS.md](./PRODUCTION-DEFAULTS.md); extra queued-job isolation test.

---

## Amendment log

| Date | Change |
|------|--------|
| 2026-11-04 | Initial audit; standard docs; doc/stub fixes; cross-tenant CRUD test; provider auth comment |
| 2026-11-04 | Schema v3 (casts, defaults, hasMany); `ApiResponse` + `--api-envelope`; `--auth` / `route_auth`; CI e2e Laravel 12 |
| 2026-11-04 | Schema v4; idempotency middleware; `route_auth_web`; production defaults doc; tenancy queue isolation test |
| 2026-11-04 | Idempotency 409/lock/fingerprint; AUTHENTICATION.md; ListPerPage; [HARDENING.md](./HARDENING.md) phase doc |
