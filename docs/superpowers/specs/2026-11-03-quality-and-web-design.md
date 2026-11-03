# Phase 8 — Blade CRUD + Quality Pass — Design Spec

**Date:** 2026-11-03
**Status:** Approved (owner: "ensure … I can work with Blade and with API JSON responses … handle all cases"; quality pass approved). Design calls in Part A are the builder's, marked *(my call)*.
**Builds on:** Phases 1–7.
**Supports:** PHP 8.3+, Laravel 12 and 13; all four profiles.
**Authority:** `docs/ARCHITECTURE.md` §1.5–1.6 ("Blade is the default for normal pages … all frontend options use the same backend Actions and Queries"), FEATURES/SETTINGS/TENANCY docs.

---

# Part A — Blade (web) CRUD

## A1. Intent

`make:crud` can generate server-rendered Blade pages as well as (or instead of) the JSON API, sharing the same Requests, Data, Actions, Query, Policy and Model, so a project can be Blade-only, API-only or both. Every case that works for the API works for the web: validation, authorization, guests, missing records, feature gates, tenants.

## A2. Command

`make:crud {module} {entity} [--fields=] [--feature=] [--api] [--web]`

- Neither flag → `--api` (Phase 5 behavior, unchanged output).
- `--web` → web pieces; `--api --web` → both.
- `--web` requires `<module>/routes/web.php` (made by `make:module --web`), with the same refusal shape as the API one: `Module "<M>" has no routes/web.php. … or create the module with make:module <M> --web[ --central].`
- API-only pieces (API controller, Resource, API route line, API test) are written only with `--api`; shared pieces always.

## A3. Generated web pieces (for `make:crud Orders Order --web`)

| Piece | Path |
|---|---|
| Web controller | `app/Modules/Orders/Controllers/OrderPageController.php` *(my call: keeps the API's `OrderController` name and path unchanged)* |
| Views | `resources/views/orders/orders/{index,create,edit,show,_form}.blade.php` — `<module kebab>/<entity plural kebab>/` *(my call: Laravel's standard view path, no Provider changes)* |
| Layout | `resources/views/layouts/blueprint.blade.php`, created once, skipped if it exists *(my call)*; views use `@extends(config('blueprint.layout'))`, config key `blueprint.layout` default `layouts.blueprint` |
| Route | `Route::resource('orders', OrderPageController::class)` + same `->missing()` (403 guests / 404 users) + `{{ middleware }}` feature gate, appended to `routes/web.php`; stub `web-route.stub` |
| Tests | `tests/Feature/Modules/Orders/OrderWebTest.php` (Pest; tenant variant on tenant modules) |

**Route names** *(my call)*: web routes keep Laravel's plain names (`orders.index`); API route lines generated from now on are named `api.orders.*` (`->names('api.orders')`), so both can exist and `route:cache` works. If `--api --web` and the (possibly published) API route stub doesn't produce `names(`, refuse (duplicate names would break `route:cache`).

**Controller** (same flow as API; `Gate::authorize` for index/show/create/edit/destroy, Requests authorize store/update):
- `index(ListOrders)` → view `orders.orders.index` with paginator.
- `create()` → view `create`; `store(StoreOrderRequest, CreateOrder)` → redirect to `show` with flash `status` = `Order created.`
- `show(Order)`, `edit(Order)` → views.
- `update(UpdateOrderRequest, Order, UpdateOrder)` → redirect to `show` with `Order updated.`
- `destroy(Order, DeleteOrder)` → redirect to `index` with `Order deleted.`
- Validation failures: FormRequest's standard redirect back with errors and old input (same Requests as the API, which returns 422 JSON).

**Views** (plain semantic HTML, no CSS framework, all output escaped with `{{ }}`):
- `index`: table (id + columns + actions), pagination links, "New" link, flash status.
- `show`: definition list of fields, Edit link, Delete form (`@csrf @method('DELETE')`).
- `create`/`edit`: form with `@csrf` (+ `@method('PUT')` on edit) including `_form`.
- `_form` inputs per type, each `value="{{ old('<col>', … ) }}"` and `@error('<col>')` message:
  - `string` → `<input type="text">`; `int` → `type="number" step="1"`; `float` → `type="number" step="any"`;
  - `bool` → hidden `0` + checkbox `1` (`@checked(old(...))`);
  - `date` → `type="datetime-local"`, edit value `format('Y-m-d\TH:i')`;
  - `array` → `<textarea>` one value per line *(my call)*; the Store/Update Requests convert a **string** `<col>` to an array of trimmed non-empty lines in `prepareForValidation()` only when the request is not JSON (`! $this->isJson()`), so API behavior is unchanged.
- Labels: `Str::headline(column)`.

**Guests and errors:** same policy (guests 403), same `->missing()` (guests 403, users 404), feature gate 403 — Laravel renders these as HTML error pages for browsers and JSON for API/`Accept: application/json` (verified by tests).

**Tenants:** tenant modules load `routes/web.php` with `['web', 'tenant']` already (Phase 6); web tests use `tenantUrl()`. Central modules use the central group.

## A4. Tests (generated web test, both variants)

guest 403 on index/create/show; missing record: guest 403, user 404; index lists; create page 200; store redirects to show, row exists; store with empty input → redirect back with session errors for each non-nullable field; show/edit 200 with escaped values; update redirects, row changed; destroy redirects to index, row gone; `--feature` → enable in `beforeEach` + "403 when disabled".

## A5. Package tests

Golden files for the web controller and `_form`; flags matrix (none / `--api` / `--web` / both) writes exactly the right files; refusals (no `routes/web.php`; old API stub without `names(` when both); `php -l` on PHP files and Blade compile (`Blade::compileString`) on views; end-to-end in Testbench for `saas` (web + api same entity, HTML and JSON responses, validation redirect, route:cache-compatible names) and `tenant-saas` (tenant web CRUD on tenant domain, 404 on central).

---

# Part B — Quality pass

1. **Static analysis + style:** add `larastan/larastan` and `laravel/pint` (dev); PHPStan level 6 over `src/` (baseline only for documented exceptions); Pint `laravel` preset over `src/` and `tests/`; composer scripts `test`, `analyse`, `format`, `check` (all three).
2. **Whole-package review:** fresh reviewers for (a) security, (b) consistency (messages, exit codes, naming), (c) cross-feature combinations; every accepted finding fixed with a failing test first.
3. **`bin/e2e`:** builds fresh Laravel apps per profile (`basic`, `modular`, `saas`, `tenant-saas`), installs this package from the local path, generates module + CRUD (`--api --web`, `--feature` where applicable), migrates (+ `tenant:create` / `tenants:migrate`), runs `php artisan test`, `route:cache`; exit non-zero on any failure; `--keep` to leave apps for inspection.
4. **CI:** `.github/workflows/ci.yml` — matrix PHP 8.3/8.4 × Laravel 12/13: `composer check`.
5. **Docs/agent kit refresh:** Cursor rules, AGENTS/CLAUDE/GEMINI templates, `laravel-engineering` skill, repo `README.md` and `AGENTS.md` reflect the shipped commands (not "coming"); `rules.md` untouched.
6. **Version `0.8.0`.**

Done = `composer check` green on Laravel 12 and 13, and `bin/e2e` green for all four profiles.
