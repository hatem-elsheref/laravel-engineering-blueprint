# Generator stubs

Templates rendered by the package's Artisan generators. Placeholders (`{{ ... }}`) are filled by PHP; do not fill them by hand.

To customize, publish them with `php artisan vendor:publish --tag=blueprint-stubs` (copied to `stubs/blueprint/`, used automatically) or point `blueprint.stub_path` at your own folder. A stub that leaves a placeholder unfilled, no longer declares the expected class, drops the required `->names('api.{{ uri }}')`, or would drop a requested feature gate or permissions mode is refused before anything is written.

The copy of this folder that `bin/leb-install` puts in `laravel-engineering-blueprint/stubs/` is reference only; the commands never read it.

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
| `policy.stub` | `make:crud` — policy (signed-in users allowed, guests denied) |
| `policy-permissions.stub` | `make:crud --permissions` — policy whose methods call `checkPermissionTo()` on `<uri>.view/create/update/delete` (`{{ definitions }}`: the definitions class name, `{{ sync }}`: the sync command); must keep the `checkPermissionTo(` calls |
| `controller.stub` | `make:crud --api` — API resource controller |
| `route.stub` | `make:crud --api` — route added to the module's `routes/api.php`; must keep `->names('api.{{ uri }}')`; its `->missing()` gives guests 403 for unknown ids so they can't probe which records exist (you may add e.g. `->middleware('auth:sanctum')` here, or on the module's `Provider.php` API group) |
| `resource.stub` | `make:crud --api` — API resource |
| `test.stub` | `make:crud --api` — Pest API tests |
| `test-tenant.stub` | `make:crud --api` in a tenant module (tenant-saas) — Pest API tests on a real test tenant |
| `web-controller.stub` | `make:crud --web` — `<Entity>PageController` (Blade pages) |
| `web-route.stub` | `make:crud --web` — `Route::resource(...)` added to the module's `routes/web.php` |
| `view-index.stub` | `make:crud --web` — `index.blade.php` (list) |
| `view-create.stub` | `make:crud --web` — `create.blade.php` |
| `view-edit.stub` | `make:crud --web` — `edit.blade.php` |
| `view-show.stub` | `make:crud --web` — `show.blade.php` |
| `view-form.stub` | `make:crud --web` — `_form.blade.php` (fields shared by create and edit) |
| `layout.stub` | `make:crud --web` — `resources/views/layouts/blueprint.blade.php`, created once (config `blueprint.layout`) |
| `test-web.stub` | `make:crud --web` — Pest web tests |
| `test-web-tenant.stub` | `make:crud --web` in a tenant module (tenant-saas) — Pest web tests on a real test tenant |
| `tenant-model.stub` | `engineering:install --profile=tenant-saas` — `app/Models/Tenant.php` |
| `tenant-routes.stub` | `engineering:install --profile=tenant-saas` — empty `routes/tenant.php` group |
| `features-definitions.stub` | `engineering:install` (saas, tenant-saas) — `app/Shared/Features/Features.php` |
| `settings-definitions.stub` | `engineering:install` (saas, tenant-saas) — `app/Shared/Settings/Settings.php` |
| `features-migration.stub` | `engineering:install` — `features` table (tenant migrations in tenant-saas) |
| `settings-migration.stub` | `engineering:install` — `settings` table (tenant migrations in tenant-saas) |

The four test stubs take `{{ permissionUses }}` (inside `uses(...)`), `{{ permissionSetup }}` (end of `beforeEach`) and `{{ permissionTest }}` (end of file); all three are empty without `--permissions`. With `--permissions`, a test stub that drops them (no `grantPermissions(` or no `InteractsWithPermissions::class`) is refused.
