# make:dto + make:action (Phase 4) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `make:dto` and `make:action` generators for existing modules, sharing `make:module`'s safety rules, plus update the kit skill to use the commands.

**Architecture:** Extract the module-level checks from `MakeModuleCommand` into `Support\Modules` (profile gate, `module_path` validation/namespace, case-insensitive lookups). `Support\Fields` parses `--fields`. Each command renders via `StubRenderer`, validates (namespace + class), then writes.

**Tech Stack:** PHP 8.3, Laravel 12 + 13, Testbench ^10 || ^11, Pest 4.

**Spec:** `docs/superpowers/specs/2026-11-03-make-dto-action-design.md`

## Global Constraints

- Every refusal: exit 1, nothing written, messages exactly as spec §5–7.
- Generated code exactly as spec §6/§7 examples (4-space indent, trailing newline, no blank import block when there are no imports, imports sorted alphabetically).
- `make:module` tests stay green and unchanged.
- Done = `vendor/bin/pest` green on Laravel 13 and 12, output shown.

## Review Focus

1. `--fields="this:string"` — `$this` as a property is a PHP fatal. → Task 2 `rejects reserved names`.
2. `--fields=""` / `--data=""` (empty option) — must behave like the option was not given, not like an invalid entry. → Task 2 `empty spec means no fields`, Task 4 `empty data option means none`.
3. Class type with an invalid segment (`Carbon\1Bad`, `App\\Double`) — must be refused, not generated. → Task 2 `rejects invalid class types`.
4. `make:dto Orders Data` (name that is only the suffix) — tip logic must not produce `Tip: … like Data.` oddities; exit 0. → Task 3 `name equal to suffix`.
5. Module given with different case **and** class created twice — second refused regardless of case. → Task 3 `refuses existing class`.

---

### Task 1: `Support\Modules` (extract from `make:module`)

**Files:**
- Create: `src/Support/Modules.php`
- Modify: `src/Console/MakeModuleCommand.php` (use it; behavior identical)
- Test: `tests/Unit/ModulesTest.php`

**Interfaces:**
- Produces `final class Modules` (constructed with no args; reads config at call time):
  - `profileError(): ?string` — the two `make:module` messages.
  - `pathError(): ?string` — `module_path must be inside app/ (got "<raw>").` or null.
  - `path(): string` — trimmed relative path, e.g. `app/Modules`.
  - `namespace(): string` — e.g. `App\Modules`.
  - `static entry(string $directory, string $name): ?string` — existing entry (file or dir) in `$directory` matching `$name` case-insensitively; null if directory missing or no match.

- [ ] **Step 1: Failing tests** — `profileError` for null/basic/modular; `pathError` for `app/Modules` (null), `app/Modules/` (null), `src/Modules`, `app/My-Modules`, `app/../x`, `app` (errors); `path()`/`namespace()` for `app/Modules/` → `app/Modules` / `App\Modules`, `app/Domain/Modules` → `App\Domain\Modules`; `entry()` finds `Orders` for `orders`, finds a file, returns null for missing dir.
- [ ] **Step 2: Run** `vendor/bin/pest tests/Unit/ModulesTest.php` → FAIL, class not found.
- [ ] **Step 3: Implement; refactor `MakeModuleCommand` to use it** (delete its private `profileError`, `validModulePath`, `existingModule`).
- [ ] **Step 4: Run** `vendor/bin/pest` → all green, `MakeModuleCommandTest` untouched.
- [ ] **Step 5: Commit** `refactor: extract module checks into Support\Modules`

---

### Task 2: `Support\Fields`

**Files:**
- Create: `src/Support/Fields.php`, `src/Support/FieldException.php` (`final class FieldException extends \InvalidArgumentException` with public readonly `string $entry`, `string $reason`; message `Invalid field "<entry>": <reason>.`)
- Test: `tests/Unit/FieldsTest.php`

**Interfaces:**
- Produces `Fields::parse(?string $spec): list<array{name: string, type: string, import: ?string}>` — `type` is what goes in the property (`?string`, `CarbonImmutable`, `?Status`); `import` is the FQCN without leading `\`, or null. Null/blank spec → `[]`. Throws `FieldException`.

- [ ] **Step 1: Failing tests**
  - `parses scalars` — `"name:string, quantity:int,note:?string"` → names `name,quantity,note`, types `string,int,?string`, imports all null.
  - `camel-cases names` — `unit_price:int` → `unitPrice`.
  - `parses class types` — `placedAt:Carbon\CarbonImmutable` and `at:?\Carbon\CarbonImmutable` → types `CarbonImmutable` / `?CarbonImmutable`, import `Carbon\CarbonImmutable`.
  - `empty spec means no fields` — `null`, `''`, `'  '` → `[]`.
  - Refusals dataset (assert entry + reason fragment): `"name:string,,x:int"` (entry `""`, `empty`), `"name"` (`missing type`), `"1x:int"` (`invalid name`), `"name:text"` (`unknown type text`), `"a:int,a:string"` (`duplicate name a`), `"a:Foo\Status,b:Bar\Status"` (`two class types named Status`), `rejects reserved names` `"this:string"` (`reserved name this`), `rejects invalid class types` `"a:Carbon\1Bad"` and `"a:App\\\\Double"` (`unknown type`).
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement.** Scalars: `string,int,float,bool,array`. Class type: contains `\`, every segment `^[A-Za-z_][A-Za-z0-9_]*$`. **Step 4: Run** `vendor/bin/pest` → green.
- [ ] **Step 5: Commit** `feat: field list parser for make:dto`

---

### Task 3: `make:dto`

**Files:**
- Create: `src/Console/MakeDtoCommand.php`
- Modify: `stubs/dto.stub` (rewrite: `{{ namespace }}`, `{{ imports }}`, `{{ class }}`, `{{ properties }}`), `src/BlueprintServiceProvider.php`
- Test: `tests/Feature/MakeDtoCommandTest.php` — `beforeEach`: profile `modular`, `module_path` `app/Modules`, `make:module Orders`, save providers file; `afterEach`: delete `app_path('Modules')`, `base_path('stubs')`, restore providers file.

**Interfaces:**
- Consumes: `Modules`, `Fields`, `ModuleName::normalize` (used for class names too), `StubRenderer`.
- Produces: signature per spec §6; messages per §5. Stub `{{ imports }}` is either `''` or the `use` lines followed by a blank line — the stub places it so both give the spec's exact output.

- [ ] **Step 1: Failing tests**
  - `generates the spec example` — exact content of spec §6 example.
  - `generates without imports` — `--fields="name:string"` exact: no `use`, `namespace …;` then one blank line then `final readonly class`.
  - `generates an empty constructor` — no `--fields` → constructor body `        // public string $name,`.
  - `refuses invalid fields` — `--fields="name:text"` → exit 1, `Invalid field "name:text": unknown type text.`, no `Data/` folder.
  - `resolves module case-insensitively` — module `orders` → file in `Orders/Data`.
  - `refuses missing module` — `Billing` → `Module "Billing" not found in app/Modules. Create it with php artisan make:module Billing.`
  - `refuses existing class` — dataset `OrderData` / `orderdata` after a first run → `"OrderData" already exists at app/Modules/Orders/Data/OrderData.php.`; file unchanged.
  - `normalizes and validates class name` — `order_data` → `Using class name "OrderData".`; `1x` → `Invalid class name "1x". Use letters and numbers, starting with a letter, e.g. OrderData.`
  - `shows naming tip` — `Order` → `Tip: data classes are usually named like OrderData.`, exit 0; `name equal to suffix` — `Data` → exit 0, no tip.
  - `refuses profile and path problems` — basic profile, `module_path` `src/Modules` → exit 1.
  - `refuses old published stub` — Phase 1 `dto.stub` (inline its content: uses `{{ moduleNamespace }}`, `{{ requestClass }}`, `{{ dtoClass }}`) in `base_path('stubs/blueprint')` → `did not produce a valid class (unknown placeholder {{ moduleNamespace }})`, nothing written.
  - `generated data class works` — spec example → `php -l` passes; `require_once` it; `new App\Modules\Orders\Data\OrderData('a', 2, null, Carbon\CarbonImmutable::now())` has `->quantity === 2`.
- [ ] **Step 2: Run** → FAIL (command not found). **Step 3: Implement.** **Step 4: Run** `vendor/bin/pest` → green.
- [ ] **Step 5: Commit** `feat: make:dto`

---

### Task 4: `make:action`

**Files:**
- Create: `src/Console/MakeActionCommand.php`
- Modify: `stubs/action.stub` (rewrite: `{{ namespace }}`, `{{ imports }}`, `{{ class }}`, `{{ parameters }}`, `{{ body }}`), `src/BlueprintServiceProvider.php`
- Test: `tests/Feature/MakeActionCommandTest.php` (same setup as Task 3; helper creates `OrderData` via `make:dto Orders OrderData --fields="name:string"`).

**Interfaces:**
- Consumes: as Task 3.
- Produces: signature per spec §7.

- [ ] **Step 1: Failing tests**
  - Exact output dataset: spec §7 example (`--data=OrderData --transaction`); no flags (`handle(): void` with `// ...`, no `use`); `--data` only (Data import only, body `// ...`); `--transaction` only (DB import only, `function (): void`).
  - `refuses missing data class` — `--data=Missing` → `Data class "Missing" not found in app/Modules/Orders/Data. Create it with php artisan make:dto Orders Missing.`, nothing written.
  - `matches data class case-insensitively` — `--data=orderdata` → imports `App\Modules\Orders\Data\OrderData`.
  - `empty data option means none` — `--data=` → same as no `--data`.
  - `refuses existing class`, `refuses missing module`, `refuses profile and path problems` (as Task 3).
  - `shows naming tip` — `CreateOrderAction` → `Tip: the Actions folder already says it is an action; consider "CreateOrder".`, exit 0.
  - `refuses old published stub` — Phase 1 `action.stub` (uses `{{ moduleNamespace }}`, `{{ actionData }}`, `{{ model }}` …) → refused.
  - `generated action works` — with `--data=OrderData --transaction`: `php -l` passes; require both files; `(new CreateOrder)->handle(new OrderData('a'))` returns null without error.
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement.** **Step 4: Run** `vendor/bin/pest` → green.
- [ ] **Step 5: Commit** `feat: make:action`

---

### Task 5: Docs + dual-version acceptance

**Files:**
- Modify: `stubs/README.md` (dto/action rows name their commands), `kit/skills/leb-generate-crud/SKILL.md`

**Interfaces:** none.

- [ ] **Step 1: Update `SKILL.md`** — line 10 and the stub table: when the package is installed, use `php artisan make:module`, `make:dto`, `make:action` (one line each with an example); the placeholder list (lines 39–43) applies only to model, migration, request, controller, resource, policy, route and test stubs; drop "Artisan commands are not wired yet".
- [ ] **Step 2: Verify** `grep -n "not\*\* wired" kit/skills/leb-generate-crud/SKILL.md` → no output; `grep -c "make:dto\|make:action" kit/skills/leb-generate-crud/SKILL.md` → ≥ 2.
- [ ] **Step 3: Acceptance** — `vendor/bin/pest` (Laravel 13); `composer update -W -q --no-interaction --with "laravel/framework:^12.0" && vendor/bin/pest`; restore `composer update -W -q --no-interaction`. Show both.
- [ ] **Step 4: Commit** `docs: point kit skill and stub README at the generators`
