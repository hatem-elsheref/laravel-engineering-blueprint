# Phase 5 — `make:crud` (API) — Design Spec

**Date:** 2026-11-03
**Status:** Approved
**Builds on:** Phases 3–4 (`Modules`, `ModuleName`, `PhpNames`, `Fields`, `StubRenderer`, the generator safety rules).
**Supports:** PHP 8.3+, Laravel 12 and 13.
**Authority:** `docs/ARCHITECTURE.md` (§1.2 layout, §1.3 class roles, §1.4 module rules, §1.5 request flow), `docs/NAMING.md`, `docs/CONSTITUTION.md` ("Requests: validation + authorization; Data classes for typed transfer").

---

## 1. Intent

One command produces a complete, working, tested **JSON API CRUD** for one entity inside an existing module, following the blueprint's request flow (Route → Request → Data → Controller → Action/Query → Model → Resource). The generated code must pass its own generated tests in a fresh Laravel app right after `php artisan migrate`, and read like code a careful developer following the blueprint would write.

## 2. Scope decisions (owner)

- **API only.** Blade/Livewire/Vue screens are a later phase.
- Feature on/off gating (Phase 7) and tenancy wiring (Phase 6) are not part of this phase; they plug into this output later.

## 3. Goals

`php artisan make:crud <Module> <Entity> [--fields=...]` creates, for `make:crud Orders Order --fields="name:string,quantity:int,note:?string"`:

| Piece | Path | Class |
|---|---|---|
| Model | `app/Modules/Orders/Models/Order.php` | `App\Modules\Orders\Models\Order` |
| Migration | `database/migrations/<Y_m_d_His>_create_orders_table.php` | anonymous |
| Factory | `database/factories/Orders/OrderFactory.php` | `Database\Factories\Orders\OrderFactory` |
| Requests | `Requests/StoreOrderRequest.php`, `Requests/UpdateOrderRequest.php` | |
| Data | `Data/OrderData.php` | with `fromRequest()` and `toArray()` |
| Actions | `Actions/CreateOrder.php`, `UpdateOrder.php`, `DeleteOrder.php` | |
| Query | `Queries/ListOrders.php` | |
| Controller | `Controllers/OrderController.php` | |
| Resource | `Resources/OrderResource.php` | |
| Policy | `Policies/OrderPolicy.php` | auto-discovered by Laravel |
| Route | one line added to the module's `routes/api.php` | `Route::apiResource('orders', OrderController::class)` |
| Tests | `tests/Feature/Modules/Orders/OrderApiTest.php` | Pest |

Then prints each `created …` line, `added route to app/Modules/Orders/routes/api.php`, and `Next: php artisan migrate && php artisan test --filter=OrderApiTest`.

## 4. Non-goals

- Web screens; nested/parent resources (`--parent`); soft deletes; search/filter/sort; relationships; enums; file uploads; feature gating; tenancy.
- Partial updates (PATCH semantics) — `update` replaces all fields (same rules as store; PUT and PATCH both route there).
- Choosing an authentication guard or installing Sanctum (see §6.4).
- Regenerating or merging into existing files.

## 5. Inputs and refusals

Every refusal exits 1 and writes nothing. Order:

1. Profile gate, `module_path` check, module lookup — same as Phase 4 (`make:dto`), same messages.
2. **Entity name** — `ModuleName::normalize` + not `PhpNames::isReserved` → else `Invalid entity name "<input>". Use a singular name, e.g. Order.`; changed → `Using entity name "<Entity>".` **Must be singular** *(builder-check call: `apiResource` derives the route parameter by singularizing the URI, so a plural entity breaks model binding)*: `Str::singular($entity) !== $entity` → `Entity names must be singular, e.g. <singular>.`
3. **Fields** — `Fields::parse` (same syntax as `make:dto`), plus CRUD limits: the only allowed class type is `Carbon\CarbonImmutable` (nullable allowed). Other class types → `Invalid field "<entry>": make:crud supports string, int, float, bool, array and Carbon\CarbonImmutable.` Field names whose snake_case is `id`, `created_at` or `updated_at` → `Invalid field "<entry>": <column> is created automatically.` No `--fields` is allowed (id + timestamps only).
4. **Route file** — `<module>/routes/api.php` must exist → else `Module "<Module>" has no routes/api.php. Add it and load it in its Provider (see docs), or create the module with make:module <Module> --api.`
5. **Collisions** — any target file in §3 already exists (case-insensitive file name per folder), or any migration file ending `_create_<table>_table.php` exists in `database/migrations`, or `routes/api.php` already contains `<Entity>Controller` → `"<relative path>" already exists. make:crud never overwrites files.` (first collision found).
6. **Render all files**; any `StubException`, or a rendered class file without its expected `namespace …;` and `class <Name>` → `Stub <path> did not produce a valid class (<reason>). Update or delete it, then re-run.`
7. **Write** all files, then append the route (§6.6). If a write fails midway, delete the files this run created, restore `routes/api.php`, and report the error (exit 1).

Names derived from `<Entity>` (e.g. `Order`, `PurchaseOrder`):
- table / URI: `Str::snake(Str::pluralStudly($entity))` / `Str::kebab(Str::pluralStudly($entity))` → `orders` / `purchase-orders`. (Pluralizing is right here: the entity is singular.)
- variable / route parameter: `Str::camel($entity)` / `Str::snake($entity)` → `$order` / `{order}` (apiResource default).
- columns, request keys, resource keys: `Str::snake(field)`; Data properties: camelCase field names (as in `make:dto`).

## 6. Generated code (decided shape)

### 6.1 Field type mapping

| Field type | Migration | Cast | Rules (store & update) | Factory | Data from request |
|---|---|---|---|---|---|
| `string` | `string(col)` | — | `required`, `string`, `max:255` | `fake()->words(3, true)` | `$request->string(key)->toString()` |
| `int` | `integer(col)` | `integer` | `required`, `integer`, `between:-2147483648,2147483647` | `fake()->numberBetween(1, 100)` | `$request->integer(key)` |
| `float` | `decimal(col, 12, 2)` | `float` | `required`, `numeric`, `between:-9999999999.99,9999999999.99` | `fake()->randomFloat(2, 1, 1000)` | `$request->float(key)` |
| `bool` | `boolean(col)` | `boolean` | `required`, `boolean` | `fake()->boolean()` | `$request->boolean(key)` |
| `array` | `json(col)` | `array` | `present`, `array` *(builder-check call: `required` rejects `[]`)* | `[]` | `(array) $request->validated(key)` |
| `Carbon\CarbonImmutable` | `timestamp(col)` | `immutable_datetime` | `required`, `date` | `CarbonImmutable::now()` | `CarbonImmutable::parse($request->validated(key))->setTimezone(config('app.timezone'))` |

Nullable (`?type`): migration `->nullable()`; rule `nullable` instead of `required`; factory value unchanged; Data mapping returns `null` when the validated value is `null` (e.g. `$request->validated(key) === null ? null : $request->integer(key)`). Rules are written as arrays: `'quantity' => ['required', 'integer'],`.

### 6.2 Model, migration, factory

- Model: `final class Order extends Model`, `use HasFactory;`, `protected $fillable = [<columns>];`, `protected function casts(): array` with the cast column (omitted when empty), and `protected static function newFactory(): OrderFactory` returning `OrderFactory::new()`. No `$table` (Laravel's default `orders` is correct).
- Migration: `id()`, the columns in field order, `timestamps()`; `down()` drops the table.
- Factory: `Database\Factories\Orders\OrderFactory extends Factory`, `protected $model = Order::class;`, `definition()` per §6.1.

### 6.3 Request flow classes

- `StoreOrderRequest` / `UpdateOrderRequest`: `authorize()` uses the policy (`$this->user()?->can('create', Order::class) ?? false` / `$this->user()?->can('update', $this->route('order')) ?? false`); `rules()` per §6.1.
- `OrderData` (`final readonly`): constructor as `make:dto` would generate it; `public static function fromRequest(StoreOrderRequest|UpdateOrderRequest $request): self` mapping per §6.1; `public function toArray(): array` returning `[column => property]`.
- `CreateOrder::handle(OrderData $data): Order` → `Order::create($data->toArray())` inside `DB::transaction`.
- `UpdateOrder::handle(Order $order, OrderData $data): Order` → `$order->update(...)`, returns `$order->refresh()`, inside `DB::transaction`.
- `DeleteOrder::handle(Order $order): void` → `$order->delete()`, inside `DB::transaction`.
- `ListOrders::handle(int $perPage = 15): LengthAwarePaginator` → `Order::query()->latest('id')->paginate($perPage)`.
- `OrderController` (no base class, `final`): `index(ListOrders)` → `OrderResource::collection(...)`; `store(StoreOrderRequest, CreateOrder)` → resource with status 201; `show(Order)` → resource; `update(UpdateOrderRequest, Order, UpdateOrder)` → resource; `destroy(Order, DeleteOrder)` → `response()->noContent()`. `index`, `show` and `destroy` call `Gate::authorize('viewAny'|'view'|'delete', …)`; store/update are authorized by their requests.
- `OrderResource`: `id`, each column, `created_at`, `updated_at` (dates as ISO-8601 strings; `CarbonImmutable` fields too).
- `OrderPolicy`: `viewAny`, `view`, `create`, `update`, `delete`, each `return true;` with a `// Tighten for your app` comment. Methods type-hint `App\Models\User`, so **guests are always denied** (Laravel denies guests for non-nullable user parameters).

### 6.4 Authentication *(my call)*

Routes get **no authentication middleware**; the policy denies guests (403). Rationale: Laravel 12/13 apps don't have Sanctum until `php artisan install:api`, and writing `auth:sanctum` would break apps without it. The success output adds: `Note: routes are protected by OrderPolicy (guests get 403). Add auth middleware (e.g. auth:sanctum) in routes/api.php for token APIs.`

### 6.5 Tests (generated into the app)

`tests/Feature/Modules/Orders/OrderApiTest.php`, Pest, `uses(RefreshDatabase::class)`, acting as `App\Models\User::factory()->create()`:
- guest → `getJson('/api/orders')` is 403;
- index lists a factory-made order; store creates (201, row in DB); show returns it; update changes it; destroy deletes (204, row gone);
- store with empty payload → 422 with an error for each non-nullable field (skipped when all fields are nullable or there are none).

**Pest missing** *(builder-check call)*: the blueprint emits Pest tests (Phase 1 decision). If the app has no Pest (`class_exists(Pest\TestSuite::class)` is false), the test file is skipped — not written — and the output says `Skipped tests/Feature/Modules/<Module>/<Entity>ApiTest.php: Pest is not installed (composer require pestphp/pest --dev --with-all-dependencies && ./vendor/bin/pest --init).` Exit stays 0. The real-app check installs Pest first.

### 6.6 Route wiring

Append to the module's `routes/api.php`: `use <ns>\Controllers\OrderController;` after the last existing `use` line (or after `declare(strict_types=1);` if none), and `Route::apiResource('orders', OrderController::class);` at the end of the file.

### 6.7 Stubs

Phase-1 stubs `model`, `migration`, `request`, `controller`, `resource`, `policy`, `route` (now the single route line, placeholders `{{ uri }}`, `{{ controller }}`; must contain `<Controller>::class`), `test` are **rewritten** for this design (placeholders named by the builder); new stubs: `factory.stub`, `crud-data.stub`, `crud-create-action.stub`, `crud-update-action.stub`, `crud-delete-action.stub`, `query.stub`. `dto.stub` and `action.stub` (Phase 4) are not touched. `stubs/README.md` and `kit/skills/leb-generate-crud/SKILL.md` updated: the skill tells agents to use `make:crud` and drops the hand-fill section.

## 7. Testing (acceptance — green on Laravel 12 and 13)

Package tests:
1. Every file in §3 created with the expected namespace/class; route line + `use` line appended; exact content asserted for the Model, Data class, Store request and Controller of the §3 example.
2. Field mapping: one test per type row in §6.1 (migration line, cast, rule, Data mapping), and nullable variants.
3. Refusals in §5 (each one): nothing written, `routes/api.php` unchanged.
4. Generated PHP files pass `php -l`.
4a. Plural entity (`Orders`) refused; `array` field rule is `present`; Pest-missing path skips the test file with the message (simulate by binding a check the builder exposes).
5. **End-to-end:** in the Testbench app (with a test `App\Models\User` + factory fixture and sqlite in memory), generate `make:crud Orders Order --fields="name:string,quantity:int,price:?float,active:bool,tags:array,shippedAt:?Carbon\CarbonImmutable"`, run its migration, load the generated classes, and run the same HTTP scenarios as §6.5 — all pass.
6. Old published Phase-1 CRUD stub in `stubs/blueprint/` → refused.

**Real-app check (final review):** in a fresh `laravel/laravel` app with Pest installed: install → `make:module Orders --api` → `make:crud …` → `php artisan migrate` → `php artisan test` passes the generated tests.

**Phase 5 is done only when `vendor/bin/pest` is green on Laravel 12 and 13 and the output is shown.**

## 8. Left to the builder

- Placeholder names in stubs; how per-field lines are assembled.
- Test fixture approach for `App\Models\User` in Testbench.
- Exact comment text in generated files.

## Amendments (post-review fixes, 2026-11-03)

- Number rules carry ranges matching the columns (422 instead of a DB error).
- Dates are converted to `app.timezone` so offsets keep the right moment.
- Entity names that clash with an imported class in any generated file are refused.
- The controller `use` line is inserted in sorted position; CRLF route files keep CRLF.
- Generated guest test covers all five endpoints; each policy method carries `// Tighten for your app.`
- Known limit: implicit route-model binding runs before the policy, so guests get 404 for ids that don't exist and 403 for ids that do. Adding auth middleware (as the success note says) closes this.
