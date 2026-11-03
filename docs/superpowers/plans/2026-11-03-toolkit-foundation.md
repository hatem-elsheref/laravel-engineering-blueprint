# Toolkit Foundation (Phase 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the repo root into an installable Composer package `best/laravel-engineering-blueprint` with a service provider, publishable config + stubs, an `engineering:about` command, and an environment compatibility check, all proven by a green Pest suite.

**Architecture:** Package at repo root (`src/`, `config/`, `stubs/`, `tests/`). `Compatibility` is a plain class (minimums injected via constructor) returning a `CompatibilityResult` value object; the service provider binds it from `config('blueprint.support')`, and the command just renders it. Tests run on orchestra/testbench.

**Tech Stack:** PHP 8.3, Laravel 12 (`illuminate/*` ^12), orchestra/testbench ^10, pestphp/pest ^4 (PHPUnit 12).

**Spec:** `docs/superpowers/specs/2026-11-03-toolkit-foundation-design.md`

## Global Constraints

- Package name `best/laravel-engineering-blueprint`; PSR-4 `Best\Blueprint\` → `src/`, `Best\Blueprint\Tests\` → `tests/`.
- `require`: `php ^8.3`, `illuminate/support ^12.0`, `illuminate/console ^12.0`. `require-dev`: `orchestra/testbench ^10.0`, `pestphp/pest ^4.0`.
- Auto-discovery: `extra.laravel.providers` = `["Best\\Blueprint\\BlueprintServiceProvider"]`.
- Command namespace `engineering:*`. Publish tags exactly `blueprint-config` and `blueprint-stubs`.
- Config defaults exactly as spec §3.3 (`module_path` `app/Modules`, `shared_path` `app/Shared`, `stub_path` null, `profiles` `['basic','modular','saas','tenant-saas']`, `default_profile` `modular`, `support.php` `'8.3'`, `support.laravel` `'12'`).
- Do not modify `docs/` (except this plan), `kit/skills/laravel-engineering/`, `rules.md`, beyond the stub-path reference updates in Task 2.
- Done = `vendor/bin/pest` green with output shown.

**Spec correction (Task 2):** the spec lists `bin/`, `README.md`, `AGENTS.md`, `blueprint.manifest.json` as untouched and names only two skill files to update, but moving `kit/stubs` breaks `bin/leb-install` (`cp -R kit/stubs/.` under `set -e`) and leaves 6 other stale references. Task 2 updates every `kit/stubs` reference so the installer keeps working; installed bundles get `stubs/` instead of `kit/stubs/`.

## Review Focus

1. Versions with suffixes (`8.3.0-dev`, `12.0.0-beta1`, `8.4.1RC1`) must compare by their numeric part — `8.3.0-dev` meets minimum `8.3`. → Task 1 test `normalizes pre-release suffixes`.
2. Newer majors must pass a minimum check (PHP `8.4`, Laravel `13`) — the check is "at least", not "equal". → Task 1 test `passes newer versions`.
3. `engineering:about` must exit non-zero and print ✗ on an incompatible environment, not just exit 0 on the happy path. → Task 4 test `fails when requirements are not met`.
4. A project that publishes config with `support` keys removed/null must still get spec defaults, not a crash. → Task 3 test `falls back to default minimums when support config is missing`.
5. `bin/leb-install` must still produce a working bundle after the stub move. → Task 2 Step 3 install smoke run.

---

### Task 1: Composer package skeleton, test harness, Compatibility

**Files:**
- Create: `composer.json`, `phpunit.xml`, `tests/Pest.php`, `tests/TestCase.php`
- Create: `src/Support/Compatibility.php`, `src/Support/CompatibilityResult.php`
- Test: `tests/Unit/CompatibilityTest.php`
- Modify: `.gitignore` (append `/vendor/`, `composer.lock`, `.phpunit.cache/`)

**Interfaces:**
- Produces:
  - `Best\Blueprint\Support\Compatibility::__construct(string $minPhp = '8.3', string $minLaravel = '12')`
  - `Compatibility::check(?string $phpVersion = null, ?string $laravelVersion = null): CompatibilityResult` — nulls default to `PHP_VERSION` and `Illuminate\Foundation\Application::VERSION`.
  - `final readonly class CompatibilityResult { public function __construct(public bool $passed, public array $checks) }` + `failed(): array` (checks whose `passed` is false, reindexed).
  - Each check: `['name' => 'php'|'laravel', 'required' => string, 'actual' => string, 'passed' => bool]`, order php then laravel.
  - `Best\Blueprint\Tests\TestCase` (Testbench) bound to `Feature` and `Unit` in `tests/Pest.php`.

- [ ] **Step 1: Create `composer.json`, `phpunit.xml`, harness, install**

`composer.json` per Global Constraints, plus `"minimum-stability": "stable"`, `"prefer-stable": true`, `"config": {"allow-plugins": {"pestphp/pest-plugin": true}}`, `"scripts": {"test": "pest"}`. `getPackageProviders()` in `TestCase` returns `[]` for now (Task 3 adds the provider). `phpunit.xml`: testsuites `Unit` (`tests/Unit`) and `Feature` (`tests/Feature`), `cacheDirectory=".phpunit.cache"`, `<source><include><directory>src</directory></include></source>`.

Run: `composer install`
Expected: installs without conflict; `vendor/bin/pest --version` prints Pest 4.x.

- [ ] **Step 2: Write the failing tests** in `tests/Unit/CompatibilityTest.php`

```php
use Best\Blueprint\Support\Compatibility;
use Best\Blueprint\Support\CompatibilityResult;

it('passes on supported versions', function () {
    $result = (new Compatibility)->check('8.3', '12');
    expect($result)->toBeInstanceOf(CompatibilityResult::class)
        ->and($result->passed)->toBeTrue()
        ->and($result->failed())->toBe([]);
});

it('fails old versions and identifies the failing checks', function () {
    $result = (new Compatibility)->check('8.1', '10');
    expect($result->passed)->toBeFalse()
        ->and(array_column($result->failed(), 'name'))->toBe(['php', 'laravel']);
});

it('reports only the check that fails', function () {
    $result = (new Compatibility)->check('8.3.10', '11.5.0');
    expect(array_column($result->failed(), 'name'))->toBe(['laravel']);
});

it('has the documented result shape', function () {
    $result = (new Compatibility)->check('8.3.1', '12.4.0');
    expect($result->checks)->toBe([
        ['name' => 'php', 'required' => '8.3', 'actual' => '8.3.1', 'passed' => true],
        ['name' => 'laravel', 'required' => '12', 'actual' => '12.4.0', 'passed' => true],
    ]);
});

it('passes newer versions', function () {
    expect((new Compatibility)->check('8.4.0', '13.1.0')->passed)->toBeTrue();
});

it('normalizes pre-release suffixes', function () {
    expect((new Compatibility)->check('8.3.0-dev', '12.0.0-beta1')->passed)->toBeTrue()
        ->and((new Compatibility)->check('8.2.99RC1', '12')->passed)->toBeFalse();
});

it('uses the running environment by default', function () {
    $result = (new Compatibility)->check();
    expect($result->checks[0]['actual'])->toBe(PHP_VERSION)
        ->and($result->checks[1]['actual'])->toBe(\Illuminate\Foundation\Application::VERSION);
});

it('respects custom minimums', function () {
    expect((new Compatibility('99.0', '12'))->check('8.3', '12')->passed)->toBeFalse();
});
```

- [ ] **Step 3: Run to verify failure**

Run: `vendor/bin/pest tests/Unit/CompatibilityTest.php`
Expected: FAIL — `Class "Best\Blueprint\Support\Compatibility" not found`.

- [ ] **Step 4: Implement `Compatibility` and `CompatibilityResult`**

Comparison: strip each actual version to its leading numeric part (`/^\d+(\.\d+)*/`) before `version_compare($actual, $required, '>=')`; `actual` in the result keeps the original unmodified string. `passed` = all checks passed.

- [ ] **Step 5: Run to verify pass**

Run: `vendor/bin/pest`
Expected: 8 passed.

- [ ] **Step 6: Commit**

```bash
git add .gitignore composer.json phpunit.xml tests src
git commit -m "feat: package skeleton, Pest harness, compatibility check"
```

---

### Task 2: Consolidate stubs to root `stubs/`

**Files:**
- Move: `kit/stubs/*` → `stubs/*` (use `git mv kit/stubs stubs`)
- Modify (replace `kit/stubs` with `stubs`): `bin/leb-install:139-140` (source `${KIT_SOURCE}/stubs/.`, dest `${bundle}/stubs/`), `blueprint.manifest.json:19` (`"stubsRoot": "stubs"`), `README.md:14,61`, `AGENTS.md:9`, `kit/rules/cursor/laravel-engineering.mdc:20`, `kit/rules/templates/AGENTS.md:37` (→ `laravel-engineering-blueprint/stubs/`), `kit/skills/leb-generate-crud/SKILL.md:10`

**Interfaces:**
- Produces: `stubs/` at repo root containing the 11 `.stub` files + `README.md` (Task 3 publishes this directory).

- [ ] **Step 1: Move and update references**

- [ ] **Step 2: Verify no stale references**

Run: `grep -rn "kit/stubs" . --exclude-dir=.git --exclude-dir=vendor --exclude-dir=superpowers`
Expected: no output.

- [ ] **Step 3: Installer smoke test**

Run: `rm -rf /tmp/leb-smoke && mkdir /tmp/leb-smoke && ./bin/leb-install /tmp/leb-smoke --link-agents && ls /tmp/leb-smoke/laravel-engineering-blueprint/stubs/model.stub && ls -L /tmp/leb-smoke/.cursor/skills/leb-generate-crud/SKILL.md`
Expected: exits 0, both paths listed.

- [ ] **Step 4: Run suite** — `vendor/bin/pest` still green (8 passed).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "refactor: move generator stubs to package root stubs/"
```

---

### Task 3: Config + BlueprintServiceProvider

**Files:**
- Create: `config/blueprint.php` (exact array from spec §3.3), `src/BlueprintServiceProvider.php`, `src/Blueprint.php`
- Modify: `tests/TestCase.php` (`getPackageProviders()` → `[BlueprintServiceProvider::class]`)
- Test: `tests/Feature/ServiceProviderTest.php`

**Interfaces:**
- Consumes: `Compatibility::__construct(string $minPhp, string $minLaravel)` (Task 1); `stubs/` (Task 2).
- Produces:
  - `Best\Blueprint\Blueprint::NAME = 'best/laravel-engineering-blueprint'`, `Blueprint::VERSION = '0.3.0'`, `Blueprint::version(): string` — `Composer\InstalledVersions::getPrettyVersion(NAME)` when the package is installed and the result is non-null and not `dev-*` / `*no-version-set*`; otherwise `VERSION`.
  - Container binding: `Compatibility::class` singleton built from `config('blueprint.support.php') ?? '8.3'` and `config('blueprint.support.laravel') ?? '12'`.
  - Publishes: `config/blueprint.php` → `config_path('blueprint.php')` under tag `blueprint-config`; package `stubs/` → `base_path('stubs/blueprint')` under tag `blueprint-stubs`.
  - Commands registered inside `runningInConsole()` (Task 4 adds `AboutCommand` to this list).

- [ ] **Step 1: Write the failing tests**

```php
use Best\Blueprint\Blueprint;
use Best\Blueprint\BlueprintServiceProvider;
use Best\Blueprint\Support\Compatibility;
use Illuminate\Support\ServiceProvider;

it('loads the provider', function () {
    expect(app()->getProvider(BlueprintServiceProvider::class))->not->toBeNull();
});

it('merges package config', function () {
    expect(config('blueprint.module_path'))->toBe('app/Modules')
        ->and(config('blueprint.default_profile'))->toBe('modular')
        ->and(config('blueprint.support'))->toBe(['php' => '8.3', 'laravel' => '12']);
});

it('registers the config publish tag', function () {
    $paths = ServiceProvider::pathsToPublish(BlueprintServiceProvider::class, 'blueprint-config');
    expect(array_values($paths))->toBe([config_path('blueprint.php')])
        ->and(array_key_first($paths))->toEndWith('config/blueprint.php');
});

it('registers the stubs publish tag', function () {
    $paths = ServiceProvider::pathsToPublish(BlueprintServiceProvider::class, 'blueprint-stubs');
    expect(array_values($paths))->toBe([base_path('stubs/blueprint')])
        ->and(is_file(array_key_first($paths).'/model.stub'))->toBeTrue();
});

it('binds compatibility using configured minimums', function () {
    config()->set('blueprint.support', ['php' => '99.0', 'laravel' => '12']);
    app()->forgetInstance(Compatibility::class);
    expect(app(Compatibility::class)->check('8.3', '12')->passed)->toBeFalse();
});

it('falls back to default minimums when support config is missing', function () {
    config()->set('blueprint.support', null);
    app()->forgetInstance(Compatibility::class);
    $checks = app(Compatibility::class)->check('8.3', '12')->checks;
    expect(array_column($checks, 'required'))->toBe(['8.3', '12']);
});

it('reports a version string', function () {
    expect(Blueprint::version())->toBeString()->not->toBeEmpty();
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/ServiceProviderTest.php`
Expected: FAIL — provider class not found.

- [ ] **Step 3: Implement config, `Blueprint`, provider; register it in `TestCase`**

- [ ] **Step 4: Run to verify pass** — `vendor/bin/pest` → 15 passed.

- [ ] **Step 5: Commit**

```bash
git add config src tests
git commit -m "feat: service provider with publishable config and stubs"
```

---

### Task 4: `engineering:about` command

**Files:**
- Create: `src/Console/AboutCommand.php`
- Modify: `src/BlueprintServiceProvider.php` (add to `commands([...])`)
- Test: `tests/Feature/AboutCommandTest.php`

**Interfaces:**
- Consumes: `Compatibility` binding, `Blueprint::NAME`, `Blueprint::version()` (Task 3).
- Produces: signature `engineering:about`, description `Show Laravel Engineering Blueprint version and environment compatibility`. Output lines, in order:
  1. `{Blueprint::NAME} {Blueprint::version()}`
  2. one per check: `✓ PHP >= 8.3 (actual 8.3.33)` / `✗ Laravel >= 12 (actual 11.0.0)` (labels `PHP`, `Laravel`)
  3. pass: `Environment is compatible.` — fail: `Environment is not compatible: upgrade the items marked ✗.`
  Exit `self::SUCCESS` on pass, `self::FAILURE` on fail.

- [ ] **Step 1: Write the failing tests**

```php
use Best\Blueprint\Blueprint;
use Best\Blueprint\Support\Compatibility;
use Illuminate\Support\Facades\Artisan;

it('is registered', function () {
    expect(Artisan::all())->toHaveKey('engineering:about');
});

it('passes on the current environment', function () {
    $this->artisan('engineering:about')
        ->expectsOutputToContain(Blueprint::NAME)
        ->expectsOutputToContain('✓ PHP >= 8.3')
        ->expectsOutputToContain('✓ Laravel >= 12')
        ->expectsOutputToContain('Environment is compatible.')
        ->assertExitCode(0);
});

it('fails when requirements are not met', function () {
    app()->instance(Compatibility::class, new Compatibility('99.0', '12'));
    $this->artisan('engineering:about')
        ->expectsOutputToContain('✗ PHP >= 99.0')
        ->expectsOutputToContain('✓ Laravel >= 12')
        ->expectsOutputToContain('Environment is not compatible')
        ->assertExitCode(1);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/AboutCommandTest.php`
Expected: FAIL — `engineering:about` not registered.

- [ ] **Step 3: Implement `AboutCommand`, register in provider.** Resolve `Compatibility` via `handle(Compatibility $compatibility)` injection so the test's instance override applies.

- [ ] **Step 4: Run full suite (acceptance)**

Run: `vendor/bin/pest`
Expected: 18 passed, 0 failed. Paste the output into the completion report.

- [ ] **Step 5: Commit**

```bash
git add src tests
git commit -m "feat: engineering:about command"
```
