# Phase 4 — `make:dto` + `make:action` — Design Spec

**Date:** 2026-11-03
**Status:** Draft for approval
**Builds on:** Phase 3 (`make:module`, `StubRenderer`, `ModuleName`, profile gate, `module_path` validation).
**Supports:** PHP 8.3+, Laravel 12 and 13.
**Authority:** `docs/ARCHITECTURE.md` §1.2–1.3 (`Data/`, `Actions/`), `docs/NAMING.md` (`OrderData`, `CreateOrder`, `handle()`), `docs/PHASE-0.md` ("DTO: Native PHP").

---

## 1. Intent

Give each module its two most common building blocks with one command each: a **Data** class (typed, immutable transfer object in plain PHP) and an **Action** (one business operation with a single `handle()` method). Output must look like code a careful developer following the blueprint would write by hand — nothing more.

## 2. Goals

1. `make:dto <Module> <Name> [--fields=...]` → `app/Modules/<Module>/Data/<Name>.php`.
2. `make:action <Module> <Name> [--data=<DataClass>] [--transaction]` → `app/Modules/<Module>/Actions/<Name>.php`.
3. Same safety as `make:module`: profile gate, `module_path` validation, never overwrite, render and validate everything before writing, overridable stubs with the leftover-token check.
4. Naming tips (warnings only) from `docs/NAMING.md`.
5. Stubs `dto.stub` and `action.stub` rewritten to match the architecture.

## 3. Non-goals

- Building a Data object from a Form Request (`fromRequest`), Models, Requests, Controllers — Phase 5 (`make:crud`).
- Default values, validation attributes, or a DTO library (spatie/laravel-data etc.).
- Using a Data class from another module (`--data=Billing\InvoiceData`). Cross-module use goes through public actions/contracts (ARCHITECTURE §1.4).
- Editing or regenerating existing classes.

## 4. Anti-goals

- Generated code that references a class that doesn't exist.
- Overwriting a file, or creating an empty folder.
- Library dependencies in generated code.

## 5. Shared rules (both commands)

Steps in order; every refusal exits 1 and writes nothing.

1. **Profile gate** — same messages as `make:module`.
2. **`module_path`** — same validation and message as `make:module`.
3. **Module** — the `<Module>` argument is matched case-insensitively against existing entries in the module path; the existing directory's spelling is used. Not found → `Module "<input>" not found in <module_path>. Create it with php artisan make:module <Input>.`
4. **Class name** — normalized with the same rules as module names (`Str::studly`, then `^[A-Z][A-Za-z0-9]*$`); invalid → `Invalid class name "<input>". Use letters and numbers, starting with a letter, e.g. <example>.` (`OrderData` / `CreateOrder`). Changed by normalizing → `Using class name "<Name>".`
5. **Exists** — target file exists (case-insensitive name match in the target folder) → `"<Name>" already exists at <relative path>.`
6. **Render** from stubs via `StubRenderer`. A `StubException`, or rendered text without `namespace <expected>;` and `class <Name>` → `Stub <path> did not produce a valid class (<reason>). Update or delete it, then re-run.`
7. **Write** (creating `Data/` or `Actions/` only now), print `created <relative path>`.
8. **Tip** (exit stays 0) — see each command.

Shared logic (profile gate, `module_path`, module lookup) moves into one support class used by all three commands; `make:module`'s behavior and messages don't change. `ModuleName` may be generalized (e.g. `ClassName`) as long as `make:module` behaves the same.

## 6. `make:dto`

Signature: `make:dto {module : Module name, e.g. Orders} {name : Class name, e.g. OrderData} {--fields= : Comma-separated name:type list, e.g. "name:string,quantity:int,note:?string"}`

**Fields.**
- Split on `,`, trim each; each entry `name:type`.
- Name: `Str::camel()` of the given name (`unit_price` → `unitPrice`); must match `^[a-z][A-Za-z0-9]*$` afterwards.
- Type: optional leading `?` (nullable), then one of `string`, `int`, `float`, `bool`, `array`, or a fully-qualified class name containing `\` (e.g. `Carbon\CarbonImmutable`, `App\Modules\Orders\Enums\Status`; a leading `\` is allowed and stripped). Class types become a `use` import and the short name in the property; two imports with the same short name → refuse.
- Refuse (with the offending entry quoted) on: empty entry, missing `:`, invalid name, unknown type, duplicate name. Message: `Invalid field "<entry>": <reason>.`
- Class types are **not** checked for existence (they may be vendor classes or not yet written); this is the one deliberate exception to "no references to missing classes", because the user typed the full name.

**Output** (`make:dto Orders OrderData --fields="name:string,quantity:int,note:?string,placedAt:Carbon\CarbonImmutable"`):

```php
<?php

declare(strict_types=1);

namespace App\Modules\Orders\Data;

use Carbon\CarbonImmutable;

final readonly class OrderData
{
    public function __construct(
        public string $name,
        public int $quantity,
        public ?string $note,
        public CarbonImmutable $placedAt,
    ) {}
}
```

- No class types → no `use` lines and no blank line for them.
- No `--fields` → constructor body is a single commented example: `        // public string $name,`.

**Tip:** name doesn't end with `Data` → `Tip: data classes are usually named like <Base>Data.`

## 7. `make:action`

Signature: `make:action {module : Module name, e.g. Orders} {name : Class name, e.g. CreateOrder} {--data= : Data class in this module, e.g. OrderData} {--transaction : Wrap handle() in DB::transaction}`

- `--data` → normalized like a class name; must exist **exactly** as `<module>/Data/<Data>.php` (case-insensitive match is accepted and its real spelling used). Missing → `Data class "<Data>" not found in <module>/Data. Create it with php artisan make:dto <Module> <Data>.`

**Output** (`make:action Orders CreateOrder --data=OrderData --transaction`):

```php
<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Data\OrderData;
use Illuminate\Support\Facades\DB;

final class CreateOrder
{
    public function handle(OrderData $data): void
    {
        DB::transaction(function () use ($data): void {
            // ...
        });
    }
}
```

- No `--data` → `handle(): void` and `function (): void` (no `use ($data)`), no Data import.
- No `--transaction` → body is just `        // ...` and no `DB` import.
- No imports at all → no `use` lines and no blank line for them.

**Tip:** name ends with `Action` → `Tip: the Actions folder already says it is an action; consider "<Name without Action>".`

## 8. Stubs

| Stub | Change |
|---|---|
| `stubs/dto.stub` | Rewritten for §6 with `{{ namespace }}`, `{{ imports }}`, `{{ class }}`, `{{ properties }}`. |
| `stubs/action.stub` | Rewritten for §7 with `{{ namespace }}`, `{{ imports }}`, `{{ class }}`, `{{ parameters }}`, `{{ body }}`. Phase 5 adds its own CRUD-specific action stubs. |
| `stubs/README.md` | Rows updated. |
| `kit/skills/leb-generate-crud/SKILL.md` | *(Builder-check call)* Updated so agents use `make:module`, `make:dto` and `make:action` when the package is installed, instead of hand-filling `module-provider.stub`, `dto.stub` and `action.stub` (whose placeholders are now filled by PHP). Hand-filling stays documented only for the stubs not yet owned by a command (model, migration, request, controller, resource, policy, route, test). |

Published Phase 1 versions of these stubs hit the leftover-token refusal (§5.6).

*(Builder-check call)* Phase 5 ships its own CRUD Data stub (with `fromRequest`) instead of re-rendering `dto.stub`, so this phase's placeholder set stays as is and published copies keep working.

## 9. Testing (acceptance — green on Laravel 12 and 13)

1. `make:dto` exact output for: the §6 example; no fields; only scalar fields (no `use`, no blank line).
2. Field parsing: `unit_price:int` → `$unitPrice`; `\Carbon\CarbonImmutable` works; each refusal case (empty entry, `name`, `1x:int`, `name:text`, duplicate, two classes with the same short name) → exit 1, nothing written, message quotes the entry.
3. `make:action` exact output for: §7 example; no flags; `--data` only; `--transaction` only.
4. `--data` missing → exit 1, nothing written; `--data=orderdata` when `OrderData.php` exists → uses `OrderData`.
5. Module missing; module given as `orders` → resolves `Orders`.
6. Existing class (same and different case) → exit 1, file unchanged.
7. Profile `basic`/none → exit 1; invalid `module_path` → exit 1.
8. Tips print and exit 0.
9. Old Phase 1 `dto.stub`/`action.stub` published → refused, nothing written.
10. Generated files pass `php -l`; a generated Data class and Action can be loaded and called (`new OrderData(...)`, `(new CreateOrder)->handle($data)`) in the test app.
11. All Phase 3 `make:module` tests still pass unchanged.

**Phase 4 is done only when `vendor/bin/pest` is green on Laravel 12 and 13 and the output is shown.**

## 10. Left to the builder

- Name and shape of the shared support class; whether `ModuleName` is generalized.
- How imports are sorted (alphabetical is fine).
