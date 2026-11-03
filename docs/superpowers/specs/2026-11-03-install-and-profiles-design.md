# Phase 2 — `engineering:install` + Project Profiles — Design Spec

**Date:** 2026-11-03
**Status:** Approved
**Builds on:** Phase 1 (`docs/superpowers/specs/2026-11-03-toolkit-foundation-design.md`) — package `best/laravel-engineering-blueprint`, `BlueprintServiceProvider`, `config/blueprint.php`, `Compatibility`, `engineering:about`.
**Supports:** PHP 8.3+, Laravel 12 and 13.

---

## 1. Intent

After `composer require best/laravel-engineering-blueprint`, a developer runs one command — `php artisan engineering:install` — and their Laravel app is set up as one of four project types. The choice is saved in the app's config so every later command (`make:module` in Phase 3, `make:crud` in Phase 5, tenancy in Phase 6, features/settings in Phase 7) knows what kind of project it is working in and behaves accordingly.

Phase 2 is the **decision point and the record of it**, not the heavy setup. Each later phase does the real work for its own capability.

## 2. Goals

1. `engineering:install` runs the Phase 1 compatibility check first and stops (exit 1) if it fails.
2. The user picks a profile interactively (default `modular`) or with `--profile=<name>`.
3. The choice is written to the app's `config/blueprint.php` as `'profile' => '<name>'`.
4. Safe to re-run: never overwrites user files without `--force`; reports what was created, updated, and skipped.
5. Ends with a short summary and the next command to run.
6. Profiles are a single source of truth in code that later phases query ("does this project use tenancy?") instead of string-comparing names.

## 3. Non-goals (this phase)

- Installing or configuring `stancl/tenancy` (Phase 6).
- Feature or Settings tables, models, or helpers (Phase 7).
- Generating any module, class, route, or migration (Phases 3–5).
- Copying AI agent files (`AGENTS.md`, `CLAUDE.md`, Cursor rules) into the app — `bin/leb-install` already does this.
- Uninstall / downgrade command.

## 4. Anti-goals (failure even if it "works")

- Silently overwriting a user's edited `config/blueprint.php`.
- Creating empty folders or placeholder classes (violates `docs/ARCHITECTURE.md` §1.2: "Do not create empty folders or unnecessary classes").
- Hanging in CI waiting for input.
- A profile that claims a capability (e.g. tenancy) the app doesn't actually have yet, *without saying so* in the summary.

## 5. Profiles

| Profile | Modules (`app/Modules`, `app/Shared`) | Features + Settings | Tenancy (stancl, DB per tenant) |
|---|---|---|---|
| `basic` | no | no | no |
| `modular` (default) | yes | no | no |
| `saas` | yes | yes | no |
| `tenant-saas` | yes | yes | yes |

Each profile is a strict superset of the one above it.

### 5.1 Code shape (decided)

`Best\Blueprint\Support\Profile` — a string-backed PHP enum:

```php
enum Profile: string
{
    case Basic = 'basic';
    case Modular = 'modular';
    case Saas = 'saas';
    case TenantSaas = 'tenant-saas';

    public function usesModules(): bool;   // Modular, Saas, TenantSaas
    public function usesFeatures(): bool;  // Saas, TenantSaas
    public function usesSettings(): bool;  // Saas, TenantSaas
    public function usesTenancy(): bool;   // TenantSaas
    public function label(): string;       // human label for the prompt, e.g. "Modular — modules in app/Modules"
    public static function current(): ?self; // from config('blueprint.profile'); null if unset or invalid
}
```

The enum replaces the `profiles` config list as the source of truth. The `profiles` key is removed from `config/blueprint.php`.

### 5.2 Config changes

```php
'profile' => null,            // NEW: set by engineering:install; null = not installed
'default_profile' => 'modular', // existing: the installer's default choice
// 'profiles' => [...]        // REMOVED: Profile enum is the source of truth
```

## 6. `engineering:install` behavior

Signature: `engineering:install {--profile= : basic, modular, saas or tenant-saas} {--force : Overwrite the published config file}`

Steps, in order:

1. **Compatibility.** Run `Compatibility::check()`. On fail: print the ✗ lines (same format as `engineering:about`) and `Install aborted: environment is not compatible.`; exit 1. Nothing is written.
2. **Choose profile.**
   - `--profile` given → validate against `Profile::tryFrom`. Invalid → `Unknown profile "<x>". Choose one of: basic, modular, saas, tenant-saas.`; exit 1.
   - Not given, interactive → `choice` prompt listing the four labels. Default = the saved profile (`Profile::current()`) if there is one, else `config('blueprint.default_profile')`, else `modular` if that value isn't a valid profile.
   - Not given, `--no-interaction` → use that same default silently. A re-run without `--profile` therefore keeps the saved profile.
   - **Downgrade guard** *(builder-check call)*: if the chosen profile drops a capability the saved profile has (e.g. `tenant-saas` → `modular`), interactive mode asks `This removes <capabilities> from the project. Continue?` (default no); `--no-interaction` refuses with `Refusing to drop <capabilities> without --force.` and exits 1 unless `--force` is given. Nothing is written on refusal.
3. **Write config** (`config_path('blueprint.php')`):
   - File absent → publish the package config, then set `'profile' => '<name>'`. Report `created config/blueprint.php`.
   - File present, `--force` → republish (overwrite) the package config, then set the profile. Report `overwrote config/blueprint.php`.
   - File present, no `--force`:
     - It has a top-level `'profile' => <literal>,` line, where `<literal>` is `null` or a single- or double-quoted string → replace only that value. Report `updated profile: <old> → <new>` (`<old>` shown as `none` when `null`), or `profile unchanged: <name>` if equal (nothing written).
     - It has no such line — either no `'profile'` key (published under Phase 1) or a non-literal value such as `env('BLUEPRINT_PROFILE', 'saas')` *(builder-check call: never rewrite expressions the user wrote)* → do not edit; print `Could not record the profile in config/blueprint.php. Re-run with --force or set 'profile' => '<name>' manually.`; **no summary**; exit 1, because nothing was recorded *(builder-check call)*.
   - The rest of the file is untouched byte-for-byte.
   - **Config cache** *(builder-check call)*: if the app's config is cached (`app()->configurationIsCached()`) and the file was written, run `config:clear` and report `cleared config cache` so later commands see the new profile.
4. **Folders.** None are created *(owner delegated; chosen to honor ARCHITECTURE.md "no empty folders")*. Module folders are created by `make:module` (Phase 3) when the first module is generated — in line with "no empty folders".
5. **Summary.** Print:
   - `Installed profile: <label>`
   - One line per capability: `✓ Modules`, `✓ Features`, `✓ Settings`, `✓ Tenancy`, or `– <Capability> (not in this profile)`.
   - An included capability whose phase hasn't shipped yet gets a suffix: Modules `(coming in Phase 3)`, Tenancy `(coming in Phase 6)`, Features and Settings `(coming in Phase 7)`. In Phase 2 none have shipped, so every included capability carries its suffix. Keep this as one capability → phase map so later phases delete entries as they ship.
   - Next step line *(builder-check call: never point at a command that doesn't exist yet)*: if the profile uses modules **and** Modules has shipped → `Next: php artisan make:module <Name>`; otherwise → `Next: php artisan engineering:about`. Driven by the same capability → phase map.
   - Exit 0.

The command must never prompt when `--no-interaction` is set.

## 7. Effect on existing code

- `config/blueprint.php`: add `profile`, remove `profiles` (§5.2).
- `BlueprintServiceProvider`: register `InstallCommand`.
- `engineering:about`: add one line after the version: `Profile: <name>` or `Profile: not installed (run php artisan engineering:install)`. Exit code unchanged (profile state does not affect compatibility).
- `tests/Feature/ServiceProviderTest.php`: Phase 1 assertions about config keys stay valid (`module_path`, `default_profile`, `support`).

## 8. Testing (acceptance criteria — all must pass on Laravel 12 and 13)

Run with `vendor/bin/pest`. Feature tests use a temp `config_path` (Testbench app) and clean the published file between tests.

1. **ProfileTest (unit)** — capability matrix in §5 exactly; `tryFrom('nope')` is null; `current()` returns null for unset and invalid config, the enum for valid.
2. **InstallCommandTest**
   - `--profile=saas --no-interaction` on a clean app → config file created, contains `'profile' => 'saas'`, exit 0, summary shows `✓ Features`.
   - No `--profile`, `--no-interaction` → uses `modular`.
   - Interactive: answers the choice prompt with `tenant-saas` → written.
   - Invalid `--profile=enterprise` → exit 1, message lists valid profiles, no file written.
   - Incompatible environment (bind `Compatibility('99.0','12')`) → exit 1, no file written.
   - Re-run with a different profile → only the profile line changes; a custom edit elsewhere in the file (e.g. `module_path` changed to `src/Modules`) survives.
   - Re-run with same profile → `profile unchanged`, file mtime/content unchanged.
   - Existing Phase-1-style file without a `profile` line, no `--force` → file unchanged, "could not record" message, no summary, exit 1.
   - Existing file with `'profile' => env('BLUEPRINT_PROFILE', 'saas'),` → file unchanged, exit 1.
   - Re-run with no `--profile`, `--no-interaction`, saved `saas` → stays `saas` (not `modular`).
   - Saved `tenant-saas`, `--profile=modular --no-interaction` → exit 1, file unchanged; same with `--force` → written.
   - Config cached before install → cache cleared after write.
   - Summary for `modular` in Phase 2 → `Next: php artisan engineering:about`.
   - `--force` → custom edit is gone, profile is set.
   - No folders are created under `app/`.
3. **AboutCommandTest** — shows `Profile: not installed` by default and `Profile: saas` when config has it.

**Phase 2 is done only when `vendor/bin/pest` is green on both Laravel 12 and 13 and the output is shown.**

## 9. Left to the builder

- Exact wording of profile labels and the `(coming soon)` marker, within the formats above.
- Whether config writing lives in the command or a small `ConfigWriter` class (keep it small either way).
- Prompt implementation (`$this->choice` vs `laravel/prompts`) — must respect `--no-interaction`.
