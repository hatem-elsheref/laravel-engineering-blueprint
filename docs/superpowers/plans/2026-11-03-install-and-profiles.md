# Install + Profiles (Phase 2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `Profile` enum and an `engineering:install` command that records the chosen project profile in the app's `config/blueprint.php`, safely and re-runnably, and show the profile in `engineering:about`.

**Architecture:** `Profile` (enum) is the single source of truth for capabilities. `ConfigWriter` owns all file work (publish package config, rewrite only a literal `'profile'` line) and returns a `ProfileWrite` outcome. `InstallCommand` orchestrates: compatibility → choose → downgrade guard → write → cache clear → summary.

**Tech Stack:** PHP 8.3, Laravel 12 + 13, Testbench ^10 || ^11, Pest 4.

**Spec:** `docs/superpowers/specs/2026-11-03-install-and-profiles-design.md`

## Global Constraints

- Profile values exactly: `basic`, `modular`, `saas`, `tenant-saas`. Default `modular`.
- Capability matrix exactly spec §5; capability names in output: `Modules`, `Features`, `Settings`, `Tenancy`.
- Upcoming map (in `InstallCommand`): `Modules` → 3, `Tenancy` → 6, `Features` → 7, `Settings` → 7; suffix format ` (coming in Phase N)`.
- No folders are created by install.
- Never prompt when non-interactive. Never rewrite a non-literal `'profile'` value.
- Done = `vendor/bin/pest` green on Laravel 13 (default install) **and** Laravel 12 (`composer update -W --with "laravel/framework:^12.0"`), output shown.

## Review Focus

1. Published config whose `'profile'` line has a trailing comment (`'profile' => 'saas', // set by install`) — still a literal; must update and keep the comment. → Task 2 test `keeps trailing comment`.
2. Two top-level `'profile'` lines (hand-edited duplicate) — ambiguous; must be unrecordable, not "first wins". → Task 2 test `refuses duplicate profile keys`.
3. `--profile=SaaS` (wrong case) or `--profile=" saas"` — must be rejected with the valid list, not silently mapped. → Task 3 test `rejects wrong-case profile`.
4. `default_profile` set to an invalid value in a published config — install must fall back to `modular`, not crash. → Task 3 test `falls back when default_profile invalid`.
5. Config written but in-memory config stale — a second install in the same process must see the new profile. → Task 4 test `rerun keeps saved profile`.

---

### Task 1: `Profile` enum + config keys

**Files:**
- Create: `src/Support/Profile.php`
- Modify: `config/blueprint.php` (add `'profile' => null,` as the first key; remove `'profiles'`)
- Test: `tests/Unit/ProfileTest.php`

**Interfaces:**
- Produces: `enum Profile: string` (cases `Basic`, `Modular`, `Saas`, `TenantSaas`) with `usesModules(): bool`, `usesFeatures(): bool`, `usesSettings(): bool`, `usesTenancy(): bool`, `capabilities(): list<string>` (subset of `['Modules','Features','Settings','Tenancy']` in that order), `label(): string`, `static current(): ?self` (from `config('blueprint.profile')`, null when unset/invalid/non-string), `static values(): list<string>`.

- [ ] **Step 1: Failing tests**

```php
it('matches the capability matrix', function (Profile $p, array $caps) {
    expect($p->capabilities())->toBe($caps)
        ->and($p->usesModules())->toBe(in_array('Modules', $caps))
        ->and($p->usesFeatures())->toBe(in_array('Features', $caps))
        ->and($p->usesSettings())->toBe(in_array('Settings', $caps))
        ->and($p->usesTenancy())->toBe(in_array('Tenancy', $caps));
})->with([
    [Profile::Basic, []],
    [Profile::Modular, ['Modules']],
    [Profile::Saas, ['Modules', 'Features', 'Settings']],
    [Profile::TenantSaas, ['Modules', 'Features', 'Settings', 'Tenancy']],
]);

it('lists values in order', fn () => expect(Profile::values())->toBe(['basic', 'modular', 'saas', 'tenant-saas']));
it('rejects unknown values', fn () => expect(Profile::tryFrom('nope'))->toBeNull());
it('has a label per profile', fn () => expect(array_map(fn ($p) => $p->label(), Profile::cases()))->each->not->toBeEmpty());

it('reads the current profile from config', function ($value, $expected) {
    config()->set('blueprint.profile', $value);
    expect(Profile::current())->toBe($expected);
})->with([[null, null], ['enterprise', null], [42, null], ['saas', Profile::Saas]]);

it('ships config with profile unset and no profiles list', function () {
    expect(config('blueprint.profile'))->toBeNull()
        ->and(config()->has('blueprint.profiles'))->toBeFalse();
});
```

- [ ] **Step 2: Run** `vendor/bin/pest tests/Unit/ProfileTest.php` → FAIL, `Profile` not found.
- [ ] **Step 3: Implement enum; edit config.**
- [ ] **Step 4: Run** `vendor/bin/pest` → all green (Phase 1 tests still pass).
- [ ] **Step 5: Commit** `feat: Profile enum and profile config key`

---

### Task 2: `ConfigWriter`

**Files:**
- Create: `src/Support/ConfigWriter.php`, `src/Support/ProfileWrite.php`
- Test: `tests/Unit/ConfigWriterTest.php` (work in a fresh temp dir per test; delete after)

**Interfaces:**
- Consumes: `Profile` (Task 1).
- Produces:
  - `final readonly class ProfileWrite { __construct(public string $status, public ?string $old = null) }`; status constants `ProfileWrite::UPDATED = 'updated'`, `UNCHANGED = 'unchanged'`, `UNRECORDABLE = 'unrecordable'`. `$old` = previous literal value (`null` when it was `null`).
  - `ConfigWriter::__construct(Illuminate\Filesystem\Filesystem $files)`
  - `ConfigWriter::publish(string $target): void` — copy package `config/blueprint.php` to `$target` (overwrites; creates dir).
  - `ConfigWriter::setProfile(string $path, Profile $profile): ProfileWrite` — matches exactly one line `/^(?:    |\t)'profile'\s*=>\s*(null|'[^'\n]*'|"[^"\n]*")\s*,/m`; zero or >1 matches → `UNRECORDABLE`, file untouched; equal value → `UNCHANGED`, file untouched; else replace only the value with `'<value>'` → `UPDATED`. Everything else byte-for-byte.

- [ ] **Step 1: Failing tests** — one `it` each:
  - `publishes the package config` — `publish($tmp.'/config/blueprint.php')`; file equals package `config/blueprint.php`.
  - `sets profile on a fresh publish` — after publish, `setProfile(..., Saas)` → status `updated`, old `null`, file contains `'profile' => 'saas',`.
  - `keeps other edits` — replace `'app/Modules'` with `'src/Modules'` in the file, `setProfile(Modular)` → `src/Modules` still present.
  - `reports unchanged` — profile already `'saas'`, `setProfile(Saas)` → `unchanged`, file content identical.
  - `reports old value` — `'modular'` → `setProfile(Saas)` → old `'modular'`.
  - `keeps trailing comment` — line `    'profile' => 'saas', // set by install` → `setProfile(Basic)` → line is `    'profile' => 'basic', // set by install`.
  - `refuses missing key` — file without a profile line → `unrecordable`, unchanged.
  - `refuses env expression` — `    'profile' => env('BLUEPRINT_PROFILE', 'saas'),` → `unrecordable`, unchanged.
  - `refuses duplicate profile keys` — two literal lines → `unrecordable`, unchanged.
  - `ignores nested profile keys` — only line is 8-space indented `        'profile' => 'x',` inside an array → `unrecordable`.
- [ ] **Step 2: Run** `vendor/bin/pest tests/Unit/ConfigWriterTest.php` → FAIL, class not found.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `vendor/bin/pest` → green.
- [ ] **Step 5: Commit** `feat: ConfigWriter for safe profile recording`

---

### Task 3: `engineering:install` — first install

**Files:**
- Create: `src/Console/InstallCommand.php`
- Modify: `src/BlueprintServiceProvider.php` (register command; bind nothing new — `ConfigWriter` autowires)
- Test: `tests/Feature/InstallCommandTest.php` (`beforeEach`/`afterEach` delete `config_path('blueprint.php')` and `app()->getCachedConfigPath()`; reset `config('blueprint.profile')` to null)

**Interfaces:**
- Consumes: `Compatibility`, `Profile`, `ConfigWriter`, `ProfileWrite`.
- Produces: signature `engineering:install {--profile= : basic, modular, saas or tenant-saas} {--force : Overwrite the published config file}`. On successful write it also sets in-memory `config(['blueprint.profile' => $value])`. Prompt text: `Which project profile?` with choices `Profile::values()`. Output strings exactly as spec §6; summary lines `Installed profile: <label>`, `✓ <Cap>[ (coming in Phase N)]` / `– <Cap> (not in this profile)`, `Next: …`.

- [ ] **Step 1: Failing tests** (all with `'--no-interaction' => true` unless interactive):
  - `installs with an explicit profile` — `--profile=saas` → exit 0; file has `'profile' => 'saas',`; output contains `created config/blueprint.php`, `✓ Features (coming in Phase 7)`, `– Tenancy (not in this profile)`, `Next: php artisan engineering:about`.
  - `uses modular by default` — no `--profile` → file has `'profile' => 'modular',`.
  - `falls back when default_profile invalid` — `config(['blueprint.default_profile' => 'nope'])` → `modular`.
  - `asks interactively` — `expectsQuestion('Which project profile?', 'tenant-saas')` → file has `'tenant-saas'`.
  - `rejects unknown profile` — `--profile=enterprise` → exit 1, output `Unknown profile "enterprise". Choose one of: basic, modular, saas, tenant-saas.`, file absent.
  - `rejects wrong-case profile` — `--profile=SaaS` → exit 1, file absent.
  - `aborts on incompatible environment` — bind `new Compatibility('99.0', '12')` → exit 1, output `Install aborted: environment is not compatible.`, file absent.
  - `creates no folders` — after install, `is_dir(app_path('Modules'))` and `is_dir(app_path('Shared'))` false.
- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/InstallCommandTest.php` → FAIL, command not found.
- [ ] **Step 3: Implement** (file absent → `publish` then `setProfile`; present branch handled in Task 4 — for now treat present like absent only when `--force`).
- [ ] **Step 4: Run** `vendor/bin/pest` → green.
- [ ] **Step 5: Commit** `feat: engineering:install first-install flow`

---

### Task 4: `engineering:install` — re-runs, downgrade guard, config cache

**Files:**
- Modify: `src/Console/InstallCommand.php`
- Test: `tests/Feature/InstallCommandTest.php` (append)

**Interfaces:**
- Consumes: Task 3 command; `Profile::current()`; `ProfileWrite` statuses.
- Produces: confirmation text `This removes <Cap, Cap> from the project. Continue?`; refusal `Refusing to drop <Cap, Cap> without --force.`; `updated profile: <old|none> → <new>`, `profile unchanged: <name>`, `overwrote config/blueprint.php`, `cleared config cache`, unrecordable message from spec §6.3.

- [ ] **Step 1: Failing tests**
  - `rerun changes only the profile line` — install `modular`; edit file `app/Modules`→`src/Modules`; install `--profile=saas` → `updated profile: modular → saas`, `src/Modules` kept.
  - `rerun keeps saved profile` — install `saas`; install again with no `--profile` → `profile unchanged: saas`, file still `saas`.
  - `rerun with same profile leaves file untouched` — content identical before/after.
  - `force overwrites edits` — edit as above; `--profile=saas --force` → `overwrote config/blueprint.php`, `src/Modules` gone, profile `saas`.
  - `refuses downgrade without force` — install `tenant-saas`; `--profile=modular --no-interaction` → exit 1, `Refusing to drop Features, Settings, Tenancy without --force.`, file still `tenant-saas`.
  - `allows downgrade with force` — same with `--force` → profile `modular`.
  - `confirms downgrade interactively` — `expectsConfirmation('This removes Features, Settings, Tenancy from the project. Continue?', 'no')` → exit 1, unchanged; `'yes'` → written.
  - `refuses phase-1 config` — write package config with the `'profile'` line removed → exit 1, `Could not record the profile`, no `Installed profile:` in output, file unchanged.
  - `refuses env profile` — `'profile' => env('BLUEPRINT_PROFILE', 'saas'),` → exit 1, file unchanged.
  - `clears cached config` — write `<?php return [];` to `app()->getCachedConfigPath()` before install → after: file missing, output `cleared config cache`.
- [ ] **Step 2: Run** the file → new tests FAIL.
- [ ] **Step 3: Implement.** Downgrade check runs before any write: dropped = `array_diff(saved->capabilities(), chosen->capabilities())`; `--force` bypasses.
- [ ] **Step 4: Run** `vendor/bin/pest` → green.
- [ ] **Step 5: Commit** `feat: safe re-runs, downgrade guard and cache clear for install`

---

### Task 5: Profile line in `engineering:about` + dual-version acceptance

**Files:**
- Modify: `src/Console/AboutCommand.php` (line after version)
- Test: `tests/Feature/AboutCommandTest.php` (append)

**Interfaces:**
- Consumes: `Profile::current()`.
- Produces: `Profile: <value>` or `Profile: not installed (run php artisan engineering:install)`.

- [ ] **Step 1: Failing tests** — `shows profile not installed` (default) and `shows installed profile` (`config(['blueprint.profile' => 'saas'])` → `Profile: saas`); both exit 0.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Acceptance on both Laravel versions**

Run: `vendor/bin/pest` (Laravel 13) → all green. Then `composer update -W --no-interaction --with "laravel/framework:^12.0" && vendor/bin/pest` → all green. Then `composer update -W --no-interaction` to restore Laravel 13. Show both outputs.
- [ ] **Step 5: Commit** `feat: show installed profile in engineering:about`
