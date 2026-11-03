# Laravel Engineering Blueprint (LEB)

**A standardized engineering system** for building maintainable Laravel applications with humans and AI agents — delivered as three integrated parts:

1. **Engineering Standard** — approved specs in [`docs/`](docs/) (architecture, tenancy, features, boundaries).
2. **Laravel Toolkit** — package `best/laravel-engineering-blueprint`: modular monolith, `make:*` generators, features, settings, tenancy profiles.
3. **AI Engineering Kit** — `kit/` + `bin/leb-install` for Cursor, Codex, Claude, Antigravity, Gemini.

See [`docs/PRODUCT.md`](docs/PRODUCT.md), [`docs/ENGINEERING-AUDIT.md`](docs/ENGINEERING-AUDIT.md), and [`docs/ROADMAP.md`](docs/ROADMAP.md) for product model, audit findings, and remaining priorities (API envelope, idempotency middleware, schema P2 relations).

- PHP 8.3+, Laravel 12 and 13
- Version 1.0.0
- Design authority: [docs/PHASE-0.md](./docs/PHASE-0.md) (approved)

## Install

```bash
composer require best/laravel-engineering-blueprint
php artisan engineering:install --profile=modular
php artisan engineering:about
```

Production-oriented config and CRUD flags: [docs/PRODUCTION-DEFAULTS.md](./docs/PRODUCTION-DEFAULTS.md).

## Start a new project

```bash
bin/new-app ~/Sites/shop --profile=saas --locales=en,ar
```

Builds a fresh Laravel app with this package installed, Pest set up, your profile and languages configured, migrations run, and an example `Catalog` module (Product CRUD with Blade pages and JSON API) whose generated tests pass. By default the example uses **`--permissions`**, installs spatie/laravel-permission and laravel/sanctum, and sets **`route_auth` => `sanctum`** and **`route_auth_web` => `auth`** in config (see [docs/PRODUCTION-DEFAULTS.md](./docs/PRODUCTION-DEFAULTS.md)). Use **`--demo`** for the old `--open-policy` sandbox without permissions.

Options: `--profile=basic|modular|saas|tenant-saas`, `--locales=en,ar` (first = default), `--laravel=^13.0`, `--no-example`, `--demo`. For `tenant-saas` it also installs stancl/tenancy and creates a `demo` tenant (`http://demo.localhost:8000`).

## Profiles

| Profile | What you get |
|---------|--------------|
| `basic` | Package conventions, no modules |
| `modular` | Modules in `app/Modules/` (default) |
| `saas` | `modular` + features and settings in the app database |
| `tenant-saas` | `saas` + stancl/tenancy (`composer require stancl/tenancy:^3.10`), one database per tenant, features and settings in each tenant database |

`engineering:install` is safe to re-run. Moving to a profile with fewer capabilities asks for confirmation (non-interactive runs need `--allow-downgrade`, and `--force` also allows it); it warns about modules the change breaks. `--force` overwrites `config/blueprint.php` (the old file is backed up to `config/blueprint.php.bak`).

## Commands

| Command | Does |
|---------|------|
| `engineering:about` | Version and environment compatibility |
| `engineering:architecture` `[--path=]` | Scan modules for cross-module import violations ([MODULE-BOUNDARIES.md](docs/MODULE-BOUNDARIES.md)) |
| `engineering:install [--profile=] [--allow-downgrade] [--force] [--permissions]` | Set or change the project profile; `--permissions` sets up roles and permissions (needs spatie/laravel-permission) |
| `make:module {name} [--api] [--web] [--central]` | Module with `Provider.php` and optional `routes/api.php`, `routes/web.php`; `--central` = platform module (tenant-saas) |
| `make:dto {module} {name} [--fields=]` | Typed, immutable Data class |
| `make:action {module} {name} [--data=] [--transaction]` | Action (one business operation) |
| `make:crud {module} {entity} … [--nested-under=Parent]` | Full CRUD; `--schema=` YAML (see `docs/SCHEMA-DSL.md`); nested API: `make:nested-crud` / `--nested-under` |
| `make:nested-crud {module} {parent} {entity} …` | Child JSON API under a parent (`docs/NESTED-RESOURCES.md`) |
| `tenant:create {name} [--domain=] [--database=] [--existing-database]` | Create a tenant (tenant-saas), safe to re-run |
| `feature:list`, `feature:enable {key}`, `feature:disable {key}` `[--tenant=]` | Platform admin feature control |
| `permissions:sync [--prune] [--tenant=*] [--central]` | Create the defined permissions and roles; `--tenant`/`--central` for tenant-saas. A database without the permission tables is reported with the migrate command to run (exit 1) |
| `features:sync [--tenant=*]` | Add newly defined features with their defaults; without `--tenant` = all tenants |
| `setting:list`, `setting:set {key} {value}` `[--tenant=]` | Platform admin settings (value read as JSON when it parses, otherwise plain text) |

`--tenant` is required in `tenant-saas` and refused in `saas`.

Field types for `--fields`: `string`, `int`, `float`, `bool`, `array`, `Carbon\CarbonImmutable`; prefix `?` for nullable. Example: `--fields="name:string,quantity:int,note:?string"`.

## What `make:crud` generates

Example: `php artisan make:crud Orders Order --api --web`. The entity must be singular (and not uncountable for `--web`).

| Side | Files |
|------|-------|
| Shared (both sides) | `Models/Order.php`, migration, `database/factories/Orders/OrderFactory.php`, `Requests/StoreOrderRequest.php`, `Requests/UpdateOrderRequest.php`, `Data/OrderData.php`, `Actions/CreateOrder.php`, `UpdateOrder.php`, `DeleteOrder.php`, `Queries/ListOrders.php`, `Policies/OrderPolicy.php` |
| `--api` | `Controllers/OrderController.php`, `Resources/OrderResource.php`, route in `routes/api.php`, `tests/Feature/Modules/Orders/OrderApiTest.php` |
| `--web` | `Controllers/OrderPageController.php`, views in `resources/views/orders/orders/{index,create,edit,show,_form}.blade.php`, route in `routes/web.php`, `tests/Feature/Modules/Orders/OrderWebTest.php` |

- Run once with one side and add the other later; it reuses the existing fields and feature gate.
- Pages are plain Blade (no JS) and extend `config('blueprint.layout')`. The layout `resources/views/layouts/blueprint.blade.php` is created once.
- Tests are Pest; they are skipped with a message if Pest is missing.
- Default policy is **deny-by-default** (guests and signed-in users get 403 until you implement the policy). For production use `--permissions`; for local demos without permission setup use `--open-policy`. Register auth middleware on the module provider. See [`docs/SECURITY-GENERATION.md`](docs/SECURITY-GENERATION.md). Guests get 403 also for ids that do not exist.
- `--permissions` (needs `engineering:install --permissions`): the policy checks `<uri>.view/create/update/delete`, the group is added to `Permissions::groups()`, and the generated tests prove the rules. Grant it to a role in `roles()` and run `permissions:sync`. If the group already exists without an ability this CRUD checks, it warns and names it. `--owned` adds `user_id`, scopes the list to the user's records and answers 404 for records they may not view; `<uri>.any` widens what they hold to every record. See [docs/PERMISSIONS.md](./docs/PERMISSIONS.md).
- `--feature=orders` adds `->middleware('feature:orders')` to the routes (saas, tenant-saas).
- Generated web tests fail with 419 if config is cached (`php artisan optimize`). Run `php artisan optimize:clear` before tests.

## Route names

| Route | Name |
|-------|------|
| API | `api.<uri>.*` (e.g. `api.orders.index`) |
| Web | `<uri>.*` (e.g. `orders.index`) |
| Central module (tenant-saas) | `central.api.<uri>.*`, `central.<uri>.*` |

Put auth middleware for token APIs (e.g. `auth:sanctum`) in the module's `Provider.php` API route group.

## Config (`config/blueprint.php`)

| Key | Meaning |
|-----|---------|
| `profile` | Set by `engineering:install` |
| `module_path` | Module folder inside `app/` (default `app/Modules`) |
| `shared_path` | Shared folder inside `app/` (default `app/Shared`) |
| `stub_path` | Your own stubs folder (null = published `stubs/blueprint/` or package stubs) |
| `default_profile` | Profile used when none is given (`modular`) |
| `layout` | Blade layout the generated pages extend (`layouts.blueprint`) |
| `locales` | Languages the app serves (`['en']`; e.g. `['en', 'ar']`) |
| `rtl_locales` | Right-to-left languages (`ar`, `fa`, `he`, `ur`) |
| `features` | Feature definitions class (null = `<shared_path>/Features/Features`) |
| `settings` | Setting definitions class (null = `<shared_path>/Settings/Settings`) |
| `permissions` | Permission definitions class (null = `<shared_path>/Permissions/Permissions`) |
| `central_permissions` | tenant-saas: central-side definitions (null = `<shared_path>/Permissions/CentralPermissions`) |
| `super_admin` | Role that passes every permission check (`super-admin`) |
| `permission_guard` | Guard for permissions and roles (`web`); must be the User model's guard |
| `tenant_seeder` | Seeder run by `tenant:create` (`Database\Seeders\TenantSeeder`) |
| `tenant_domain` | Base tenant domain, env `BLUEPRINT_TENANT_DOMAIN`; tenants are `<name>.<base>` |
| `support.php`, `support.laravel` | Minimum supported versions |

## Customizing stubs

```bash
php artisan vendor:publish --tag=blueprint-stubs
```

Stubs are copied to `stubs/blueprint/` and used automatically (or set `blueprint.stub_path`). A stub with leftover placeholders, a missing class, a missing `->names('api.<uri>')`, or one that would drop a requested feature gate is refused before anything is written. See [stubs/README.md](./stubs/README.md).

## PHPStan module boundaries

`engineering:architecture` scans `use` imports. For deeper checks (FQCN static calls, `new`, `::class`, extends/implements), add to your app's `phpstan.neon`:

```neon
includes:
    - vendor/best/laravel-engineering-blueprint/phpstan/blueprint-module-boundaries.neon
```

Defaults match `config/blueprint.php` → `architecture.*`. See [docs/MODULE-BOUNDARIES.md](docs/MODULE-BOUNDARIES.md).

## Tenancy, features, settings

- **Tenancy** ([docs/TENANCY.md](./docs/TENANCY.md)): tenant modules use the `tenant` middleware group (subdomain); `--central` modules answer only on central domains. Tenant migrations live in `database/migrations/tenant` (`php artisan tenants:migrate`). Use `CACHE_STORE=redis` (or memcached). Test helper: `Best\Blueprint\Testing\InteractsWithTenancy`.
- **Features** ([docs/FEATURES.md](./docs/FEATURES.md)): `Feature::enabled()`, route middleware `feature:a,b`, job middleware `RequireFeature`, Blade `@feature`. Writes only through console commands.
- **Settings** ([docs/SETTINGS.md](./docs/SETTINGS.md)): typed `Setting::get()`; `Setting::set()` only for tenant-editable settings through their Gate ability.
- **Permissions** ([docs/PERMISSIONS.md](./docs/PERMISSIONS.md)): optional, needs `composer require spatie/laravel-permission` (8 or later; teams mode is not supported) then `engineering:install --permissions` and `php artisan migrate`. Roles live in `app/Shared/Permissions/Permissions.php` (`CentralPermissions` for the tenant-saas central side), written to the database by `permissions:sync`; a `super-admin` role passes every check. A tenant-saas app with existing tenants runs `php artisan migrate && php artisan tenants:migrate && php artisan permissions:sync && php artisan permissions:sync --central`. `permission_guard` must be the User model's guard. Test helper: `Best\Blueprint\Testing\InteractsWithPermissions`.

## Languages

The generated Blade pages and the package's own messages are translatable (`__()` with English keys); Arabic ships for the package strings.

```php
// config/blueprint.php
'locales' => ['en', 'ar'],          // languages the app serves
'rtl_locales' => ['ar', 'fa', 'he', 'ur'],
```

- Request language: the request's own choice first (API: `Accept-Language`; web: the visitor's pick via `/locale/{code}`, route `blueprint.locale`), then the `app.language` setting (saas and tenant-saas), then the app default.
- The layout sets `<html lang dir>` (right-to-left for Arabic) and shows a language switcher when more than one language is configured.
- `make:crud --web` adds entity and field names to `lang/<locale>.json` to translate.
- Laravel's validation messages: `php artisan lang:publish`, then add `lang/ar/validation.php`.
- Laravel's error pages (403, 404, …) show translated titles; their layout is Laravel's (`<html lang="en">`). For right-to-left error pages run `php artisan vendor:publish --tag=laravel-errors` and set `lang`/`dir` in `resources/views/errors/layout.blade.php` like the blueprint layout.

## Agent kit

`bin/leb-install` copies the docs, skills, rules and a reference copy of the stubs into a project and wires agent entry files.

```bash
# Project-local (recommended): skills, rules, docs, stubs under ./laravel-engineering-blueprint/
./bin/leb-install /path/to/your/laravel-app

# Also wire Cursor + cross-agent entrypoints in the project root
./bin/leb-install /path/to/your/laravel-app --link-agents

# Global skills only (~/.cursor/skills, ~/.agents/skills, ~/.claude/skills)
./bin/leb-install --global

# Preview
./bin/leb-install /path/to/your/laravel-app --dry-run

# Components
./bin/leb-install TARGET --only skills,rules
./bin/leb-install TARGET --skip stubs
```

The `laravel-engineering-blueprint/` folder is safe to commit. Its `stubs/` copy is reference only; the commands use the package stubs (or your published ones).

| Harness | What gets installed |
|---------|---------------------|
| **Cursor** | `.cursor/rules/*.mdc`, `.cursor/skills/` (symlinks), `AGENTS.md` |
| **Claude Code** | `~/.claude/skills/` or project skills via symlink; `CLAUDE.md` |
| **Codex / Copilot CLI** | `~/.agents/skills/` + project `AGENTS.md` |
| **Antigravity / Gemini** | Same skills path + `GEMINI.md` |
| **Any agent** | Read `docs/CONSTITUTION.md` + `AGENTS.md` |

Agents must use the commands above; they never fill stubs by hand.

## Repository layout

| Path | Purpose |
|------|---------|
| `src/`, `config/`, `stubs/` | The package |
| `tests/` | Pest tests |
| `docs/` | Phase 0 specs (approved) |
| `kit/skills/`, `kit/rules/` | Agent skills, Cursor rules, `AGENTS.md` / `CLAUDE.md` / `GEMINI.md` templates |
| `rules.md` | Long Arabic/English engineering guide |
| `bin/leb-install`, `bin/e2e` | Kit installer; end-to-end check |

## Development

```bash
composer check   # Pint, PHPStan (level 6), Pest
bin/e2e          # builds real Laravel apps for every profile
```

CI runs in `.github/workflows/ci.yml`.

## Status

All phases (0–10) are done; version 1.0.0 is prepared. Publishing steps: [RELEASING.md](RELEASING.md). Changes: [CHANGELOG.md](CHANGELOG.md); upgrading: [UPGRADE.md](UPGRADE.md).

## License

MIT — see [LICENSE](LICENSE).
