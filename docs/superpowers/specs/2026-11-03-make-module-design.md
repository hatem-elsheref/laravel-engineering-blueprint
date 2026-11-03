# Phase 3 — `make:module` — Design Spec

**Date:** 2026-11-03
**Status:** Approved
**Builds on:** Phase 1 (package, config, stubs publishing) and Phase 2 (`Profile`, `engineering:install`).
**Supports:** PHP 8.3+, Laravel 12 and 13.
**Authority:** `docs/ARCHITECTURE.md` §1.2 and §1.4, `docs/NAMING.md`.

---

## 1. Intent

`php artisan make:module Orders` creates a new business module in the shape `docs/ARCHITECTURE.md` defines, and registers it with Laravel, so the developer can start adding Actions, Models and Controllers right away. It creates the **minimum** — a provider, plus route files only when asked — because the architecture forbids empty folders and unnecessary classes. Other generators (`make:dto`, `make:action` in Phase 4, `make:crud` in Phase 5) fill modules in later.

## 2. Goals

1. Create `app/Modules/<Name>/Provider.php` (path from `config('blueprint.module_path')`).
2. `--api` / `--web` also create `routes/api.php` / `routes/web.php`, and the provider loads exactly the route files that were created.
3. Register the module provider in `bootstrap/providers.php` using Laravel's own `ServiceProvider::addProviderToBootstrapFile()` (what `make:provider` uses).
4. Refuse clearly when the project's profile has no modules, when nothing is installed, when the name is invalid, or when the module already exists.
5. Generated files come from stubs, overridable by the project.
6. `engineering:install` stops saying Modules is "coming in Phase 3", and its next step becomes `php artisan make:module <Name>` for profiles with modules.
7. Package stubs match `docs/ARCHITECTURE.md` (`Provider.php`, `routes/`, `Controllers/`).

## 3. Non-goals

- Generating Actions, Data, Models, Controllers, migrations or tests inside the module (Phases 4–5). The CRUD-style folder set in `rules.md` §make:module is superseded by ARCHITECTURE.md's "no empty folders".
- Adding routes to an existing module, renaming or deleting modules.
- `--force` overwrite of an existing module.
- Auto-discovering modules by scanning folders.
- Modules outside `app/` (e.g. `module_path` = `src/Modules`).
- Tenant-specific routing. *(Builder-check call)* The provider shape here is the stable central/normal-route shape for every profile, including `tenant-saas`. Phase 6 must add tenant routes (e.g. `routes/tenant.php`) without requiring edits to providers generated now.

## 4. Anti-goals

- Empty folders or placeholder classes.
- Overwriting any existing file.
- A generated module that breaks `php artisan route:cache` or `config:cache`.
- Output that claims the module is registered when it isn't.

## 5. Command

Signature: `make:module {name : Module name, e.g. Orders} {--api : Add routes/api.php} {--web : Add routes/web.php}`

Steps, in order — any refusal exits 1 and writes nothing:

1. **Profile gate.** `Profile::current()`:
   - `null` → `No project profile installed. Run php artisan engineering:install first.`
   - profile without modules (`basic`) → `The "basic" profile has no modules. Switch profile with php artisan engineering:install --profile=modular.`
2. **Module path.** `config('blueprint.module_path')` must be a relative path inside `app/` (e.g. `app/Modules`). Namespace = `App\` + the path after `app/` with `/` → `\` (`app/Modules` → `App\Modules`). Otherwise → `module_path must be inside app/ (got "<value>").`
3. **Name.**
   - Normalize with `Str::studly()` (`orders` → `Orders`, `purchase_orders` / `purchase-orders` → `PurchaseOrders`).
   - After normalizing it must match `^[A-Z][A-Za-z0-9]*$`. Otherwise → `Invalid module name "<input>". Use letters and numbers, starting with a letter, e.g. Orders.`
   - If `<module_path>/<Name>` already exists (file or folder, any case on case-insensitive filesystems — compare against existing directory names case-insensitively) → `Module "<Name>" already exists at <relative path>.`
   - If normalizing changed the input, print `Using module name "<Name>".`
4. **Create files** from stubs (§6):
   - `<module_path>/<Name>/Provider.php` — always.
   - `<module_path>/<Name>/routes/api.php` — with `--api`.
   - `<module_path>/<Name>/routes/web.php` — with `--web`.
   - Print `created <relative path>` per file.
5. **Register.** `ServiceProvider::addProviderToBootstrapFile('<Namespace>\<Name>\Provider')`.
   - Returns true → `registered App\Modules\<Name>\Provider in bootstrap/providers.php`.
   - Returns false (file missing) → warning `bootstrap/providers.php not found. Register App\Modules\<Name>\Provider manually.` The module files stay; exit 0.
   - Note: Laravel's helper rewrites the file sorted and without comments — same as `make:provider`. Accepted.
6. **Caches** *(builder-check call)*: Laravel ignores `bootstrap/providers.php` while config is cached, and the provider skips routes while routes are cached. So after registering: if the config cache file exists → run `config:clear`, print `cleared config cache`; if the route cache file exists → run `route:clear`, print `cleared route cache`. Same approach as `engineering:install`.
7. **Next step:** `Next: add Actions, Models and Controllers as you need them. See docs/ARCHITECTURE.md.`

## 6. Generated code

`Provider.php` (for `Orders` with `--api --web`):

```php
<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class Provider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware('web')->group(__DIR__.'/routes/web.php');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/routes/api.php');
    }
}
```

- Without route flags: `boot()` is omitted entirely, as is the `Route` import — an empty provider class.
- With only one flag: only that route line.

`routes/api.php` and `routes/web.php`: a `<?php`, `declare(strict_types=1);`, `use Illuminate\Support\Facades\Route;`, and one commented example using the kebab-case plural of the module as the URL (`// Route::get('/orders', ...);`). No live routes.

### 6.1 Stubs

| Stub | Purpose |
|---|---|
| `stubs/module-provider.stub` | **Rewritten** to the shape above, with `{{ namespace }}`, `{{ imports }}`, `{{ body }}` placeholders. |
| `stubs/module-routes-api.stub` | New. |
| `stubs/module-routes-web.stub` | New. |
| `stubs/route.stub` | Controller import fixed from `Http\Controllers` to `Controllers`. Otherwise untouched (used in Phase 5). |
| `stubs/README.md` | Table updated with the two new stubs. |

**Stub lookup order** (decided): `config('blueprint.stub_path')/<stub>` if set and the file exists → `base_path('stubs/blueprint/<stub>')` if it exists (what `vendor:publish --tag=blueprint-stubs` creates) → package `stubs/<stub>`. This makes published stubs take effect without extra config. One small class owns this so Phases 4–5 reuse it.

**Rendered-output check** *(builder-check call)*: Phase 1 projects may already have the *old* `module-provider.stub` published (it uses `{{ moduleNamespace }}`, declares `{{ module }}ServiceProvider`, loads `Routes/`). Using it would register a class that doesn't exist and fatal every request. So, before writing anything, the renderer refuses when the rendered text still contains any `{{ ... }}` token, and the command refuses when the rendered provider does not contain `final class Provider` (or `class Provider`) in namespace `<Namespace>\<Name>`. Message: `Stub <path> did not produce a valid module provider (<reason>). Update or delete it, then re-run.` Exit 1, nothing written. The leftover-token check lives in the shared renderer, so every later generator gets it.

## 7. Effect on existing code

- `InstallCommand`: remove `Modules` from the upcoming-phases map. Summary shows `✓ Modules` and `Next: php artisan make:module <Name>` for modular/saas/tenant-saas; `basic` keeps `Next: php artisan engineering:about`.
- `BlueprintServiceProvider`: register `MakeModuleCommand`.
- Existing Phase 2 install tests that assert `✓ Modules (coming in Phase 3)` / `Next: php artisan engineering:about` for module profiles are updated.

## 8. Testing (acceptance — green on Laravel 12 and 13)

Feature tests clean up `app/Modules` and restore `bootstrap/providers.php` after each test.

1. `make:module Orders` (profile `modular`) → `Provider.php` exists, no `routes/` folder, provider has no `boot()`, registered in `bootstrap/providers.php`, exit 0.
2. `--api` → `routes/api.php` exists, provider has the api line only; `--web` likewise; both flags → both lines.
3. Generated PHP files pass `php -l`.
4. The generated provider, when registered in the test app, actually loads its routes (a route added to the generated `routes/api.php` resolves under `/api/...`).
5. `orders` and `purchase-orders` → `Orders` / `PurchaseOrders` with the "Using module name" line.
6. Invalid names (`123`, `Order Items!`, empty) → exit 1, nothing written.
7. Existing module (including different case, `orders` when `Orders` exists) → exit 1, nothing changed.
8. Profile `basic` → exit 1; no profile → exit 1.
9. `module_path` = `src/Modules` → exit 1.
10. `bootstrap/providers.php` missing → files created, warning printed, exit 0.
11a. A published **old-format** `module-provider.stub` (with `{{ moduleNamespace }}` / `ServiceProvider` class) in `stubs/blueprint/` → exit 1, stub-path message, nothing written, `bootstrap/providers.php` unchanged.
11b. Config cache file present → removed after `make:module`, `cleared config cache` printed; route cache likewise.
11. Stub override: a file at `base_path('stubs/blueprint/module-provider.stub')` is used; `stub_path` takes precedence over it.
12. Install summary for `modular` shows `✓ Modules` (no "coming in Phase 3") and `Next: php artisan make:module <Name>`.

**Phase 3 is done only when `vendor/bin/pest` is green on Laravel 12 and 13 and the output is shown.**

## 9. Left to the builder

- How the stub placeholders are filled (simple `str_replace` is fine).
- Exact comment text inside route files.
- Whether name validation lives in the command or a tiny helper.
