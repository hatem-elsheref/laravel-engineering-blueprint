# Features + Settings (Phase 7) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Per-tenant feature flags and typed settings for `saas`/`tenant-saas`, with read-only app APIs, guarded admin writes, console commands, enforcement middleware, tenant-creation defaults and `make:crud --feature`.

**Architecture:** `FlagContext` decides where state lives (`'app'` in `saas`, current tenant id in `tenant-saas`, `null` = no context). `FeatureManager`/`SettingManager` (scoped bindings, facades `Feature`/`Setting`) read via the default DB connection and memo per context. `FeatureAdmin`/`SettingAdmin` write, guarded by `AdminGuard` (no HTTP, tenant required in `tenant-saas`). Definitions come from app classes named in config.

**Tech Stack:** PHP 8.3, Laravel 12 + 13, stancl/tenancy ^3.10 (tenant-saas), Pest 4.

**Spec:** `docs/superpowers/specs/2026-11-03-features-settings-design.md`

## Global Constraints

- Messages exactly as the spec. No write path for features outside `FeatureAdmin`. Fail safe (false/default) without context; never touch the central DB for tenant state.
- Phase 1–6 tests unchanged and green; done = `vendor/bin/pest` green on Laravel 13 and 12.

## Review Focus

1. Tenant A enabled, tenant B disabled, same request switching tenants — no memo bleed. → Task 2 `isolates tenants`.
2. Admin write from a route (HTTP) → refused. → Task 2 `refuses admin writes during http requests`.
3. Setting `int` given `"5"` / `"5.5"` / `"x"`. → Task 4 type dataset.
4. Old published `route.stub` + `--feature` → refused, nothing written. → Task 7.
5. Worker reuse: state changed between two jobs is seen by the second (scoped reset). → Task 2 `forgets state between jobs`.

---

### Task 1: Definitions, migrations, install
- Create stubs `features-definitions.stub`, `settings-definitions.stub`, `features-migration.stub`, `settings-migration.stub`; `src/Flags/FlagSetup.php` (`run(Profile): list<string>` — idempotent file generation per spec §4).
- Modify `config/blueprint.php` (`features`, `settings` keys), `InstallCommand` (run `FlagSetup` for `usesFeatures()` profiles; drop `Features`/`Settings` from `UPCOMING`; `saas` Next line; `manage-settings` hint).
- Tests `tests/Feature/FlagInstallTest.php` (saas: central migrations, files; modular: none; idempotent) and `tests/Tenancy/FlagInstallTenancyTest.php` (tenant-saas: tenant migrations).

### Task 2: `FeatureManager`, `FeatureAdmin`, guard, memo
- Create `src/Flags/FlagContext.php` (`key(): ?string`), `src/Flags/AdminGuard.php`, `src/Features/{FeatureManager,FeatureAdmin,UnknownFeature,FeatureDisabled}.php`, `src/Flags/AdminOnly.php`, `src/Facades/Feature.php`; bind `FeatureManager` as `scoped`; clear memo on `TenancyInitialized`/`TenancyEnded` when stancl present.
- Tests (saas in `tests/Feature/FeatureTest.php`, tenant-saas in `tests/Tenancy/FeatureTenancyTest.php`): enabled/disabled/missing row/unknown key; `all()`; admin enable then read; `isolates tenants`; no tenant → false; admin without tenant → `AdminOnly`; `refuses admin writes during http requests` (route calling `FeatureAdmin::enable` with `runningInConsole` forced false via `app()->instance('env'…)` alternative: bind `AdminGuard` with an `http()` override); `forgets state between jobs` (`app()->forgetScopedInstances()`).

### Task 3: Enforcement
- Create `src/Http/Middleware/EnsureFeatureEnabled.php` (alias `feature`), `src/Features/RequireFeature.php` (job middleware), Blade `@feature`; `Feature::ensure()`.
- Tests: route 403/200; unknown key → exception; job with middleware fails with `FeatureDisabled` when disabled and runs when enabled; Blade directive renders conditionally.

### Task 4: `SettingManager`, `SettingAdmin`
- Create `src/Settings/{SettingManager,SettingAdmin,SettingTypes,UnknownSetting,InvalidSettingValue,SettingNotEditable}.php`, `src/Facades/Setting.php` (scoped).
- Tests: get default/stored/typed; `set` per type dataset (valid + invalid); platform-only → `SettingNotEditable`; tenant setting without ability → refused; ability allowed/denied (Gate::define); no user → denied; `SettingAdmin` bypasses editable_by/ability but not types; tenant isolation; no tenant: get → default, set → RuntimeException.

### Task 5: Console commands
- Create `src/Console/{FeatureListCommand,FeatureEnableCommand,FeatureDisableCommand,FeaturesSyncCommand,SettingListCommand,SettingSetCommand}.php` sharing `src/Console/Concerns/RunsInFlagContext.php` (profile gate, `--tenant` rules, run inside tenant).
- Tests in saas and tenant-saas per spec §7 messages; `features:sync` adds only missing rows and keeps existing state; JSON value parsing for `setting:set`.

### Task 6: Tenant creation
- Modify `TenantSetup` (steps `Create default features`, `Create default settings` inside the tenant; skipped when definitions missing); `TenantCreateCommand` (remove Phase 7 placeholder lines).
- Tests: `tenant:create` lines; rows created with default states; re-run `already done`; test helper tenants have features.

### Task 7: `make:crud --feature`
- Modify `stubs/route.stub` (`{{ middleware }}`), `MakeCrudCommand` (`--feature`, key validation, refusal when rendered route lacks `feature:<key>`), test stubs (`{{ featureSetup }}`, `{{ featureTest }}` blocks).
- Tests: route line has `->middleware('feature:orders')`; unknown key refused; old stub refused; generated tests enable the feature and include a 403 test.

### Task 8: End-to-end, docs, acceptance
- `tests/Tenancy/FeatureCrudEndToEndTest.php`: tenant CRUD behind `feature:orders` → 403 disabled, works enabled; settings read in a tenant request.
- Docs: kit skill section, stub README rows, `Blueprint::VERSION` + manifest `0.7.0`.
- Acceptance on Laravel 13 and 12.
