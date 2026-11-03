# Blade CRUD + Quality Pass (Phase 8) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** `make:crud --web/--api` generates Blade pages and/or the JSON API on the same backend; then a whole-package quality pass.

**Spec:** `docs/superpowers/specs/2026-11-03-quality-and-web-design.md` (incl. "Builder-check calls").

## Global Constraints
- Shared pieces (Model, migration, factory, Requests, Data, Actions, Query, Policy) are identical for web and API; web adds controller/views/layout/route/tests, API adds controller/resource/route/tests.
- Every refusal before any write; `composer check` green; Laravel 12 + 13.

## Review Focus
1. `--web` then later `--api` (and the reverse) on the same entity — only the new side is written; shared files untouched.
2. Taken route name (`orders.index` from an old API line) → refused, nothing written.
3. Central module (tenant-saas) web CRUD redirects/links resolve (`central.` prefix).
4. Validation failure on the web form → redirect back with errors and old input (array, bool, date fields).
5. Same URL requested as browser (HTML) vs `Accept: application/json` — 403/404/422 render as HTML vs JSON.

## Tasks
1. **API route names** — `route.stub` → `->names('api.{{ uri }}')`; refuse API stubs without `names(`; refuse taken `api.<uri>.index`; update Phase 5–7 route-line tests.
2. **Sides** — `--api`/`--web` (default api); `CrudNames::files()` split into shared/api/web keys; add-a-side mode; web refusals (no `routes/web.php`, taken `<prefix><uri>.index`).
3. **Web stubs** — `web-controller.stub`, `views/{index,create,edit,show,_form}.stub`, `layout.stub`, `web-route.stub`, `test-web.stub`, `test-web-tenant.stub`; `Data::fromRequest` drops empty array elements; `{{ routeName }}`; golden files for the web controller and `_form`.
4. **Web end-to-end** — saas: web + api same entity; HTML vs JSON responses; validation redirect with errors/old input; flash; feature gate on web. tenant-saas: tenant web CRUD + central web CRUD (`central.` names).
5. **Whole-package review** (security / consistency / combinations) → fixes with failing tests first.
6. **`bin/e2e`** for all four profiles; **CI** workflow; **docs/agent kit** refresh; version `0.8.0`; acceptance.
