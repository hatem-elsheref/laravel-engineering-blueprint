# make:module (Phase 3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `make:module <Name> [--api] [--web]` that creates a minimal module (`Provider.php` + optional route files) from overridable stubs, registers it in `bootstrap/providers.php`, and refuses safely on bad profile, name, path, existing module or stale stubs.

**Architecture:** `StubRenderer` (shared with Phases 4–5) resolves stub paths (stub_path → published `stubs/blueprint` → package) and renders placeholders, throwing `StubException` on leftover `{{ }}` tokens. `ModuleName` normalizes/validates names. `MakeModuleCommand` orchestrates gates → render all files in memory → validate → write → register → clear caches.

**Tech Stack:** PHP 8.3, Laravel 12 + 13, Testbench ^10 || ^11, Pest 4.

**Spec:** `docs/superpowers/specs/2026-11-03-make-module-design.md`

## Global Constraints

- Every refusal: exit 1, **nothing written** (render and validate everything before the first write).
- Output strings exactly as spec §5.
- Generated module namespace: `App\` + `module_path` after `app/`, `/`→`\`.
- No folders without files in them. Never overwrite a file.
- Done = `vendor/bin/pest` green on Laravel 13 and Laravel 12, output shown.

**Plan ruling on spec §6:** the route-file example URL uses `Str::kebab($name)` (Orders → `orders`, PurchaseOrders → `purchase-orders`), not a pluralized form: module names are already plural by convention and `Str::pluralStudly('Inventory')` gives `inventories`.

## Review Focus

1. `make:module Orders` twice in a row — second must refuse, `bootstrap/providers.php` unchanged. → Task 4 `refuses existing module`.
2. Module name colliding by case on macOS (`orders` when `Orders` exists). → Task 4 `refuses existing module` dataset.
3. A published old-format stub must never lead to a registered-but-missing class. → Task 4 `refuses old published stub`.
4. `module_path` with a trailing slash (`app/Modules/`) should work like `app/Modules`. → Task 3 `accepts trailing slash in module_path`.
5. The generated provider must actually load routes in a booted app, not just look right. → Task 4 `generated provider loads its api routes`.

---

### Task 1: `StubRenderer`

**Files:**
- Create: `src/Support/StubRenderer.php`, `src/Support/StubException.php` (`final class StubException extends \RuntimeException`)
- Test: `tests/Unit/StubRendererTest.php`

**Interfaces:**
- Produces:
  - `StubRenderer::__construct(Illuminate\Filesystem\Filesystem $files)`
  - `StubRenderer::path(string $stub): string` — first existing of: `rtrim(config('blueprint.stub_path'),'/').'/'.$stub` (only when config non-empty), `base_path('stubs/blueprint/'.$stub)`, package `stubs/`.$stub. Throws `StubException("Stub {$stub} not found.")` if none.
  - `StubRenderer::render(string $stub, array<string,string> $values): string` — replaces `{{ key }}` (with single spaces, as in existing stubs) for each key; if `/\{\{\s*[\w.]+\s*\}\}/` still matches, throws `StubException` with message `Stub <path> has unknown placeholder {{ x }}.`; `StubException::$path` (public readonly) and `$reason` (`unknown placeholder {{ x }}`) let the command build its own message (Task 4).

- [ ] **Step 1: Failing tests** (create temp stub dirs; clean `base_path('stubs/blueprint')` and config after each):
  - `uses package stub by default` — `path('module-provider.stub')` ends with `/stubs/module-provider.stub` and is inside the package.
  - `prefers published stubs` — file at `base_path('stubs/blueprint/module-provider.stub')` → returned.
  - `prefers stub_path over published` — both exist, `config(['blueprint.stub_path' => $tmp])` → `$tmp/module-provider.stub`.
  - `skips stub_path when file missing there` — stub_path dir lacks file → falls back.
  - `renders placeholders` — stub `A {{ name }} B {{ name }}` + `['name' => 'X']` → `A X B X`.
  - `rejects leftover placeholders` — stub `{{ name }} {{ other }}` with only `name` → `StubException`, message contains `{{ other }}` and the stub path.
  - `throws for missing stub` — `path('nope.stub')` → `StubException`.
- [ ] **Step 2: Run** `vendor/bin/pest tests/Unit/StubRendererTest.php` → FAIL, class not found.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `vendor/bin/pest` → green.
- [ ] **Step 5: Commit** `feat: StubRenderer with override lookup and leftover-token check`

---

### Task 2: `ModuleName`

**Files:**
- Create: `src/Support/ModuleName.php`
- Test: `tests/Unit/ModuleNameTest.php`

**Interfaces:**
- Produces: `final class ModuleName { public static function normalize(string $input): ?string }` — `Str::studly(trim($input))`, then must match `/^[A-Z][A-Za-z0-9]*$/`, else `null`.

- [ ] **Step 1: Failing test** — dataset: `Orders`→`Orders`, `orders`→`Orders`, `purchase-orders`→`PurchaseOrders`, `purchase_orders`→`PurchaseOrders`, `Orders2`→`Orders2`, `123`→null, `Order Items!`→null, `''`→null, `'   '`→null, `Ord/ers`→null, `App\Orders`→null.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `vendor/bin/pest` → green.
- [ ] **Step 5: Commit** `feat: ModuleName normalization`

---

### Task 3: `make:module` core + stubs

**Files:**
- Create: `src/Console/MakeModuleCommand.php`, `stubs/module-routes-api.stub`, `stubs/module-routes-web.stub`
- Modify: `stubs/module-provider.stub` (rewrite), `stubs/route.stub` (`Http\Controllers` → `Controllers`), `stubs/README.md` (add 2 rows), `src/BlueprintServiceProvider.php` (register command)
- Test: `tests/Feature/MakeModuleCommandTest.php` — `beforeEach`: `config(['blueprint.profile' => 'modular'])`, save `bootstrap/providers.php` contents; `afterEach`: delete `app_path('Modules')`, restore providers file, delete config/route cache files and `base_path('stubs/blueprint')`.

**Interfaces:**
- Consumes: `Profile::current()`, `StubRenderer`, `ModuleName`.
- Produces: signature `make:module {name : Module name, e.g. Orders} {--api : Add routes/api.php} {--web : Add routes/web.php}`. Provider stub placeholders: `{{ namespace }}`, `{{ imports }}`, `{{ body }}`. Route stubs placeholder: `{{ url }}`.

- [ ] **Step 1: Failing tests**
  - `creates a minimal module` — `make:module Orders` → exit 0; `app_path('Modules/Orders/Provider.php')` content **exactly**:
    ```php
    <?php

    declare(strict_types=1);

    namespace App\Modules\Orders;

    use Illuminate\Support\ServiceProvider;

    final class Provider extends ServiceProvider
    {
    }
    ```
    (trailing newline); `is_dir(app_path('Modules/Orders/routes'))` false; output has `created app/Modules/Orders/Provider.php`, `registered App\Modules\Orders\Provider in bootstrap/providers.php`, `Next: add Actions, Models and Controllers as you need them. See docs/ARCHITECTURE.md.`; providers file contains `App\Modules\Orders\Provider::class`.
  - `creates api and web routes` — `--api --web` → Provider content exactly the spec §6 sample; both route files exist; each contains `use Illuminate\Support\Facades\Route;` and `// Route::get('/orders'`.
  - `creates only the requested route file` — dataset `--api` / `--web`: provider contains only the matching `Route::middleware(...)` line; other route file absent.
  - `normalizes the name` — `purchase-orders` → `app/Modules/PurchaseOrders/Provider.php`, output `Using module name "PurchaseOrders".`
  - `rejects invalid names` — dataset `123`, `Order Items!` → exit 1, output starts `Invalid module name`, `app/Modules` absent, providers file unchanged.
  - `requires a profile with modules` — dataset `null` → `No project profile installed. Run php artisan engineering:install first.`; `basic` → `The "basic" profile has no modules. Switch profile with php artisan engineering:install --profile=modular.`; exit 1, nothing written.
  - `requires module_path inside app` — `config(['blueprint.module_path' => 'src/Modules'])` → exit 1, `module_path must be inside app/ (got "src/Modules").`
  - `accepts trailing slash in module_path` — `app/Modules/` → exit 0, namespace `App\Modules\Orders`.
- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/MakeModuleCommandTest.php` → FAIL, command not found.
- [ ] **Step 3: Implement stubs and command.** Render all files to strings first; then `ensureDirectoryExists` + `put` each; then `ServiceProvider::addProviderToBootstrapFile`.
- [ ] **Step 4: Run** `vendor/bin/pest` → green.
- [ ] **Step 5: Commit** `feat: make:module creates and registers a minimal module`

---

### Task 4: `make:module` safety

**Files:**
- Modify: `src/Console/MakeModuleCommand.php`
- Test: `tests/Feature/MakeModuleCommandTest.php` (append)

**Interfaces:**
- Produces: output strings `Module "<Name>" already exists at app/Modules/<Dir>.`, `Stub <path> did not produce a valid module provider (<reason>). Update or delete it, then re-run.`, `bootstrap/providers.php not found. Register App\Modules\<Name>\Provider manually.`, `cleared config cache`, `cleared route cache`.

- [ ] **Step 1: Failing tests**
  - `refuses existing module` — dataset: create `Orders` first, then `Orders` / `orders` → exit 1, already-exists message, providers file identical to after the first run.
  - `refuses old published stub` — put the Phase 1 provider stub (copy from `git show 1466c9f:stubs/module-provider.stub` content inlined in the test) at `base_path('stubs/blueprint/module-provider.stub')` → exit 1, output contains `did not produce a valid module provider`, `app/Modules` absent, providers file unchanged.
  - `refuses stub without Provider class` — override stub with no leftover tokens but `final class Thing` → exit 1, same message.
  - `uses a valid override stub` — override = package stub plus a `// custom` line → exit 0, file contains `// custom`.
  - `warns when bootstrap providers file is missing` — delete providers file → exit 0, Provider.php created, warning text shown, no `registered` line.
  - `clears caches` — create config and route cache files → after run both gone; output has both `cleared …` lines.
  - `generated files are valid php` — `--api --web`, then `php -l` each (via `Symfony\Component\Process\Process`) → exit code 0.
  - `generated provider loads its api routes` — `make:module RouteCheck --api`; append `Route::get('/route-check', fn () => 'ok');` to its `routes/api.php`; `require_once` the Provider; `app()->register('App\Modules\RouteCheck\Provider')`; `$this->get('/api/route-check')->assertOk()->assertSee('ok')`.
- [ ] **Step 2: Run** → new tests FAIL.
- [ ] **Step 3: Implement.** Existence check: case-insensitive compare of `<Name>` against directory names in the module path. Provider validity: rendered text contains `namespace <Namespace>\<Name>;` and matches `/\bclass\s+Provider\b/`; `StubException` from render maps to the same message with its reason.
- [ ] **Step 4: Run** `vendor/bin/pest` → green.
- [ ] **Step 5: Commit** `feat: make:module refuses unsafe states and clears caches`

---

### Task 5: Install summary + dual-version acceptance

**Files:**
- Modify: `src/Console/InstallCommand.php` (remove `'Modules' => 3` from `UPCOMING`)
- Modify: `tests/Feature/InstallCommandTest.php` (`installs with an explicit profile` now expects `✓ Modules` without suffix — assert output contains `✓ Modules` and does not contain `Modules (coming` — and `Next: php artisan make:module <Name>`)
- Test: add `basic keeps the about hint` — `--profile=basic` → `Next: php artisan engineering:about`.

- [ ] **Step 1: Update/add the tests.** **Step 2: Run** → FAIL. **Step 3: Implement.** **Step 4: Run** `vendor/bin/pest` → green.
- [ ] **Step 5: Acceptance** — `vendor/bin/pest` on Laravel 13; `composer update -W -q --no-interaction --with "laravel/framework:^12.0" && vendor/bin/pest`; restore with `composer update -W -q --no-interaction`. Show both.
- [ ] **Step 6: Commit** `feat: install points to make:module now that modules ship`
