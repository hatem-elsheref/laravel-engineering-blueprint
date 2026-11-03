# Validation standard

Part of the LEB runtime standard. Phase 0 flow: [ARCHITECTURE.md](./ARCHITECTURE.md) §1.5.

## Ownership

| Layer | Owns |
|-------|------|
| **Form Request** | HTTP authorization (`authorize()`), input validation (`rules()` and Laravel hooks) |
| **Action / domain** | Business invariants that validation cannot express (stock, state machines, cross-aggregate rules) |
| **Policy** | Whether the user may perform the ability on a model |

**Never** validate in controllers. **Do not** duplicate the same input rules inside Actions.

## Generated CRUD (`make:crud`)

- **Store/Update** requests use `stubs/request.stub` (or `request-update-owned.stub` with `--owned`).
- `authorize()` delegates to `$this->user()?->can(...)` matching the generated policy ability.
- `rules()` is built from field types (`CrudField`) or schema YAML ([SCHEMA-DSL.md](./SCHEMA-DSL.md)).
- Enums use `Rule::enum(...)`; field `unique: true` uses `unique:table,column` on store and `Rule::unique(...)->ignore($this->route(...))` on update.

## Laravel features to use (hand-written requests)

Use native Form Request capabilities before custom engines:

- `authorize()`, `rules()`, `messages()`, `attributes()`
- `prepareForValidation()`, `validationData()`, `passedValidation()`
- `withValidator()`, `after()` when conditional or cross-field rules need it
- Nested arrays, wildcards (`items.*.id`), `exists`, `unique`, `Rule::` objects
- Localized attribute names via `attributes()` and `lang/*/validation.php`

## Business rules vs input validation

| Input validation | Business rule |
|------------------|---------------|
| Type, length, format, required/nullable | “Cannot ship a cancelled order” |
| FK exists in DB | “Only warehouse manager for this site” |
| Unique SKU string | “SKU reserved by pending transfer” |

Enforce business rules in Actions (or domain services) **after** the request passes validation. Return **422** with a clear message for predictable business failures when exposing an API; use exceptions or result types as your app standardizes.

## API validation failures

Generated API tests expect Laravel’s default **422 Unprocessable Entity** with the standard `errors` bag for validation failures. A custom envelope ([STANDARDS-BACKLOG.md](./STANDARDS-BACKLOG.md)) is not applied by the generator today — see [API-RESPONSES.md](./API-RESPONSES.md).

## Security

- Validation does **not** replace authorization — always pair with policy/permissions ([SECURITY-GENERATION.md](./SECURITY-GENERATION.md)).
- Tenant-scoped `exists` rules must use the **tenant** connection when applicable.
