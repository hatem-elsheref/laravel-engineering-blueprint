# Nested API resources

Child entities that belong to a parent (order lines, invoice items) use **nested** `Route::apiResource` registration.

## Command

```bash
php artisan make:nested-crud Orders Order OrderLine --fields="sku:string,quantity:int"
# or
php artisan make:nested-crud Orders Order OrderLine --schema=orders/order_line.yaml
```

Equivalent to:

```bash
php artisan make:crud Orders OrderLine --api --nested-under=Order ...
```

## Generated behavior

- Adds `{parent_snake}_id` `foreignId` when the schema/`--fields` list does not already include it.
- API routes: `/api/{parents}/{parent}/{{children}}` with route names `api.{parents}.{parent}.{children}.*`.
- List query scopes rows to the parent id from the route (`$request->route('order')`).

## Limits

- **API only** — no nested Blade pages in v1.
- Parent CRUD must exist in the same module.
- Composite primary keys on the child: use single-column route binding or hand-written routes ([SCHEMA-DSL.md](./SCHEMA-DSL.md)).
