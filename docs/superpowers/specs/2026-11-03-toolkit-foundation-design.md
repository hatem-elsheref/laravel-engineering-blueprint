# Laravel Engineering Blueprint — Design Spec

**Date:** 2026-11-03
**Status:** Approved
**Scope of this doc:** Overall product decomposition + **Phase 1 (Toolkit Foundation)** detailed design.

Later phases (2–10) each get their own spec → plan → build cycle and are only summarized here.

---

## 1. Product direction (decided)

Build a **real, executable Composer package** — not a markdown/stub agent kit.

- Package: `best/laravel-engineering-blueprint`, namespace `Best\Blueprint\`.
- Deliverables: (A) the Composer package, (B) a starter Laravel app.
- The existing `docs/` specs (ARCHITECTURE, NAMING, TENANCY, FEATURES, SETTINGS, CONSTITUTION) are the **completed Phase 0** foundation and feed the implementation.
- The existing `kit/` (markdown skills, Cursor rules, templates), `rules.md`, and `bin/leb-install` remain as the secondary AI-agent layer; they are **not** removed.

### Decided engineering defaults

| Decision | Value | Rationale |
|---|---|---|
| Repo layout | **Package at repo root** (`composer.json`, `src/`, `config/`, `stubs/`, `tests/` at top level); starter app in `starter/` (Phase 9) | User choice; cleanest for publishing the package standalone |
| Test framework | **Pest** for both package tests and generator-emitted CRUD tests | Matches Laravel 11/12 defaults |
| Primary support target | **PHP 8.3+, Laravel 12** | Matches available environment (PHP 8.3.33, Laravel installer 5.31) |
| Package test harness | **orchestra/testbench** | Standard for Laravel package testing |
| Command namespace | `engineering:*` | Master prompt uses `engineering:install` |

---

## 2. Decomposition / roadmap

Phase 0 = existing `docs/` specs (done). Each remaining phase is its own spec → plan → build → test → report, gated on passing acceptance tests.

| Phase | Deliverable |
|---|---|
| **1 (this spec)** | Package foundation: `composer.json`, ServiceProvider, config, command registration, compatibility check, Pest + Testbench harness |
| 2 | `engineering:install` + project profiles |
| 3 | `make:module` + module registration |
| 4 | `make:dto`, `make:action` |
| 5 | `make:crud` (full CRUD generator) |
| 6 | `stancl/tenancy` integration + `tenant:create` |
| 7 | Features + Settings systems |
| 8 | Quality/security/full automated test pass |
| 9 | Starter app (`starter/`) |
| 10 | Release + docs + upgrade guide |

---

## 3. Phase 1 — Toolkit Foundation (detailed)

### 3.1 Goal

A versioned, installable Composer package that loads into a clean Laravel app, registers its service provider, exposes publishable config + stubs, registers at least one working Artisan command, and validates environment compatibility — all proven by a green Pest suite.

### 3.2 File layout produced

```
composer.json          # php ^8.3, illuminate/support/console ^12; dev: orchestra/testbench, pestphp/pest
                       # autoload Best\Blueprint\ -> src/ ; autoload-dev Best\Blueprint\Tests\ -> tests/
                       # extra.laravel.providers -> BlueprintServiceProvider (auto-discovery)
src/
  BlueprintServiceProvider.php   # mergeConfigFrom(config/blueprint.php); register commands in console;
                                 # publishes config (tag: blueprint-config) + stubs (tag: blueprint-stubs)
  Console/AboutCommand.php       # signature `engineering:about`; prints package version + compatibility report; exit 0 on pass
  Support/Compatibility.php      # pure class: check(php, laravel) -> structured result {passed, checks[]}
config/blueprint.php             # keys below
stubs/                           # canonical stubs (consolidated from kit/stubs)
tests/
  TestCase.php                   # extends Orchestra\Testbench\TestCase; getPackageProviders() -> [BlueprintServiceProvider]
  Pest.php                       # binds TestCase to Feature/ + Unit/
  Feature/ServiceProviderTest.php
  Feature/AboutCommandTest.php
  Unit/CompatibilityTest.php
phpunit.xml                      # testsuites Unit + Feature
```

Untouched: `docs/`, `kit/skills/`, `kit/rules/`, `rules.md`, `bin/`, `README.md`, `AGENTS.md`, `blueprint.manifest.json`.

One migration: move `kit/stubs/*` → root `stubs/*` (canonical package source for later generator phases); update the two `kit/skills/*/SKILL.md` references from `kit/stubs` to `stubs`.

### 3.3 `config/blueprint.php` keys

```php
return [
    'module_path' => 'app/Modules',
    'shared_path' => 'app/Shared',
    'stub_path'   => null, // null => package stubs; non-null => project override dir
    'profiles'    => ['basic', 'modular', 'saas', 'tenant-saas'],
    'default_profile' => 'modular',
    'support' => [
        'php'     => '8.3',   // minimum
        'laravel' => '12',    // minimum major
    ],
];
```

### 3.4 `Compatibility` contract

- `Compatibility::check(?string $phpVersion = null, ?string $laravelVersion = null): CompatibilityResult`
- Returns a small value object: `passed` (bool) and `checks` (array of `['name', 'required', 'actual', 'passed']`).
- Defaults read current `PHP_VERSION` and `Application::VERSION`. Overridable params make it unit-testable with simulated old versions.
- No exceptions for a normal "fail" — it reports; callers (installer, command) decide.

### 3.5 `engineering:about` command

- Prints: package name + version (from installed composer metadata or a constant), each compatibility check as a ✓/✗ line, and a one-line next-step hint.
- Exit code 0 when compatibility passes, non-zero when it fails (so CI/scripts can gate on it).

### 3.6 Testing (acceptance criteria — all must pass)

Run with `vendor/bin/pest`.

1. **ServiceProviderTest** — provider is loaded; `config('blueprint.module_path')` returns `app/Modules` (config merged); publish tags `blueprint-config` and `blueprint-stubs` are registered.
2. **AboutCommandTest** — `artisan('engineering:about')` is a registered command, runs, and exits 0 on the current (compatible) environment.
3. **CompatibilityTest** (unit) — `check('8.3','12')` passes; `check('8.1','10')` fails with the failing checks identified; result object shape is correct.

**Phase 1 is done only when `vendor/bin/pest` is green and the output is shown.**

### 3.7 Risks / notes

- Testbench version must match Laravel 12 support — pin `orchestra/testbench ^10` (the Laravel 12 line) in dev deps; confirm at install time.
- Package version string: read from composer's installed.json if available, else fall back to a `VERSION` constant to avoid a hard dependency.
- Stub consolidation must not break the two markdown skills — update their path references in the same change.

---

## 4. Out of scope for Phase 1

Installer command, any generator (`make:*`), tenancy, features/settings, starter app. Those are Phases 2–10.
