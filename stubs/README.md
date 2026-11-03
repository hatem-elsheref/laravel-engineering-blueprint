# Generator stubs

Templates rendered by the package's Artisan generators. Placeholders (`{{ ... }}`) are filled by PHP; do not fill them by hand.

To customize, publish them with `php artisan vendor:publish --tag=blueprint-stubs` (copied to `stubs/blueprint/`, used automatically) or point `blueprint.stub_path` at your own folder. A stub that leaves a placeholder unfilled, or no longer declares the expected class, is refused before anything is written.

| File | Used by |
|------|---------|
| `module-provider.stub` | `make:module` — module `Provider.php` |
| `module-routes-api.stub` | `make:module --api` — `routes/api.php` |
| `module-routes-web.stub` | `make:module --web` — `routes/web.php` |
| `dto.stub` | `make:dto` — Data class |
| `action.stub` | `make:action` — Action class |
| `model.stub` | `make:crud` — Eloquent model |
| `migration.stub` | `make:crud` — create-table migration |
| `factory.stub` | `make:crud` — model factory |
| `request.stub` | `make:crud` — Store/Update form requests |
| `crud-data.stub` | `make:crud` — Data class with `fromRequest()` / `toArray()` |
| `crud-create-action.stub` | `make:crud` — Create action |
| `crud-update-action.stub` | `make:crud` — Update action |
| `crud-delete-action.stub` | `make:crud` — Delete action |
| `query.stub` | `make:crud` — paginated list query |
| `controller.stub` | `make:crud` — API resource controller |
| `route.stub` | `make:crud` — route added to the module's `routes/api.php`; its `->missing()` gives guests 403 for unknown ids so they can't probe which records exist (add e.g. `->middleware('auth:sanctum')` here) |
| `resource.stub` | `make:crud` — API resource |
| `policy.stub` | `make:crud` — policy |
| `test.stub` | `make:crud` — Pest feature tests |
