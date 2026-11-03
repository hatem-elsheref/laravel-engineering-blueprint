# 6. Permissions

Status: implemented (`spatie/laravel-permission`, optional). Design record: `docs/superpowers/specs/2026-11-03-permissions-design.md`.

## 6.1 Purpose

Without permissions, a generated policy lets every signed-in user act on every record. With `make:crud --permissions` each policy method checks a named permission, and a permission no role holds is denied. A new entity is locked until an app grants access.

The package does not pick an app's roles. It supplies names, one definitions file, a sync command, a super-admin, optional ownership and tests. Each app fills in its own roles.

Permissions need `spatie/laravel-permission` 8 or later (`composer require spatie/laravel-permission`). Without it, `engineering:install --permissions` and `make:crud --permissions` refuse and print that command; everything else works. The package conflicts with spatie before 8, and `engineering:install --permissions`, `make:crud --permissions` and `permissions:sync` refuse an older version (`spatie/laravel-permission <version> is not supported; permissions need ^8.0`).

spatie's teams mode is not supported: with `permission.teams` set to `true` the same three commands refuse with "spatie teams mode (permission.teams) is not supported", and `tenant:create` skips its permissions step. Users are already separated per tenant database (6.9).

## 6.2 Setup

```bash
composer require spatie/laravel-permission
php artisan engineering:install --permissions     # combinable with --profile
php artisan migrate
php artisan permissions:sync
```

`tenant-saas` app with existing tenants: their databases do not have spatie's tables yet. Migrate them and sync both sides:

```bash
php artisan migrate && php artisan tenants:migrate && php artisan permissions:sync && php artisan permissions:sync --central
```

Until a tenant is migrated, `permissions:sync` reports `Tenant "<id>": no permission tables — run php artisan tenants:migrate` and exits 1 (the other tenants are still synced), and the super-admin check there is skipped. A permission check in that tenant still fails: spatie's own check needs the tables. The install summary prints the command above.

Re-running is safe. The command:

- creates `app/Shared/Permissions/Permissions.php` (config `blueprint.permissions`; never overwrites it);
- creates spatie's migration (`database/migrations`); in `tenant-saas` also `database/migrations/tenant`, plus `app/Shared/Permissions/CentralPermissions.php` (config `blueprint.central_permissions`);
- adds `HasRoles` to `App\Models\User`, or prints the line to add when the file cannot be edited safely (no trait line, or a `HasRoles` that is not spatie's).

Config keys: `permissions`, `central_permissions`, `super_admin` (default `super-admin`), `permission_guard` (default `web`).

`permission_guard` must be the User model's guard. spatie checks a user's permissions with the model's own guard; permissions and roles created for another guard are never found, so every check is denied (and assigning such a role throws).

spatie's configured models (`permission.models.*`) and table names (`permission.table_names.*`) are used: the sync, `tenant:create` and `InteractsWithPermissions` go through spatie's registrar.

## 6.3 Storage per profile

| Profile | Where roles and permissions live |
|---------|----------------------------------|
| `basic`, `modular`, `saas` | The app database, from `Permissions` |
| `tenant-saas`, tenant side | Each tenant database, from `Permissions` |
| `tenant-saas`, central side | The central database, from `CentralPermissions` |

The two classes never mix: tenant databases only receive `Permissions`, the central database only `CentralPermissions`.

## 6.4 The definitions class

One class is the single source of truth. Permission names are not written as strings anywhere else.

```php
final class Permissions
{
    public static function groups(): array
    {
        return ['products' => ['view', 'create', 'update', 'delete']];   // make:crud --permissions adds entries
    }

    public static function roles(): array
    {
        return [
            'admin' => ['*'],
            'editor' => ['products.view', 'products.update'],
        ];
    }
}
```

- Names are `<group>.<ability>` (see NAMING.md). The group is the CRUD's URI.
- Patterns `products.view`, `products.*` and `*` are expanded to explicit permissions at sync time. spatie's wildcard mode is not used.
- A pattern that matches no defined permission is an error (it catches typos), never a silent no-op.

## 6.5 `permissions:sync`

```bash
php artisan permissions:sync [--prune] [--tenant=*] [--central]
```

- Creates missing permissions and roles, and sets each defined role to exactly what the file says.
- Never deletes anything that is not in the file unless `--prune`; without it, they are listed as stale, with a hint that names the side (`--tenant=<id>`, `--central`).
- Clears spatie's permission cache. A definition error exits 1 and writes nothing.
- A database without spatie's permissions table is reported, not synced: `no permission tables — run php artisan migrate` (single database, `central:`) or `Tenant "<id>": no permission tables — run php artisan tenants:migrate`. Exit code 1.
- `tenant-saas`: no option syncs `Permissions` into all tenants; `--tenant=<id>` syncs one tenant; `--central` syncs `CentralPermissions` into the central database. `--central` and `--tenant` cannot be combined. `tenant:create` has a "Sync permissions" step.

## 6.6 Super-admin

The package registers a `Gate::before` that returns `true` for users with the `blueprint.super_admin` role and `null` otherwise (it never returns `false`, so it cannot block another check). It also returns `null` when the role tables are missing (a tenant not migrated yet). `permissions:sync` creates the role; assigning it is the app's job:

```php
$user->assignRole(config('blueprint.super_admin'));
```

## 6.7 `make:crud --permissions`

Refuses when permissions are not set up. Otherwise it:

- adds the entity's group (`view`, `create`, `update`, `delete`) to `groups()` in `Permissions` (`CentralPermissions` for a `--central` module);
- generates a policy where `viewAny` and `view` check `<group>.view`, and `create`, `update`, `delete` check their own permission. Guests are always denied;
- adds generated tests that create the permissions they need (`findOrCreate`) and a test role holding them, so they pass with no edit to `roles()` and without running `permissions:sync`;
- prints the next step: add the group to a role and run `permissions:sync`.

When the group is already in the definitions file (for example from a CRUD in another module with the same URI), it is not changed. If it lacks abilities this CRUD checks, `make:crud` warns and names them, e.g. `The 'orders' group in app/Shared/Permissions/Permissions.php has no 'any'; another CRUD may use it. Add 'any' to 'orders' in …`.

Adding a side to an existing CRUD must use the same permission mode as the existing code. Without the flag, `make:crud` behaves as before and prints a one-line reminder when permissions are set up.

## 6.8 `--owned`

Requires `--permissions`. Records belong to the user who creates them.

- The migration gets a `user_id` foreign key (cascade on delete); the model gets the relation; the factory creates a user.
- `user_id` is never fillable and never read from input: the create action associates the signed-in user.
- The group gets a fifth permission, `<group>.any`. It only widens the permissions a user holds to every record; alone it grants nothing.
- The policy has a fifth ability, `any()`. The list query scopes to the user's records unless `$user->can('any', Model::class)` through the Gate, so a super-admin sees every record.

On a single record:

| User | Result |
|------|--------|
| No `<group>.view` | 404 |
| Has `.view`, is not the owner, no `.any` | 404 |
| Owner or `.any` holder, lacks the action permission (`update`, `delete`) | 403 |
| Owner or `.any` holder with the permission | allowed |

A record the user cannot view is always 404, so users cannot probe which records exist; an owner without `.view` also gets 404 on their own record. The update request uses `Gate::inspect()`, so non-owners get 404 there too.

## 6.9 Tenancy

- spatie's tables exist in each tenant database and in the central database. Because users are already per database, spatie's `teams` mode is not used (and is refused, 6.1).
- The permission cache is isolated per tenant: the cache key becomes `<cache key>.tenant.<id>` while a tenant is active, and spatie's in-memory permissions are cleared explicitly (`clearPermissionsCollection()`) when tenancy starts and ends. This holds with or without stancl's cache bootstrapper.

## 6.10 Testing the app's real roles

Generated tests do not depend on the app's roles. Test them yourself with `InteractsWithPermissions` (needs spatie):

```php
use Best\Blueprint\Testing\InteractsWithPermissions;
use App\Shared\Permissions\Permissions;
use Spatie\Permission\Models\Permission;

uses(InteractsWithPermissions::class);

it('lets editors update products but not delete them', function () {
    $this->artisan('permissions:sync')->assertSuccessful();
    $editor = User::factory()->create()->assignRole('editor');

    expect($editor->can('update', $product))->toBeTrue()
        ->and($editor->can('delete', $product))->toBeFalse();
});
```

- `grantPermissions($user, ...$names)` replaces all of the user's roles with one test role holding exactly those names.
- `makeSuperAdmin($user)` adds the super-admin role.
- In `tenant-saas`, run them inside the tenant (`InteractsWithTenancy`).
