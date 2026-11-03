# 6. Permissions

Status: implemented (`spatie/laravel-permission`, optional). Design record: `docs/superpowers/specs/2026-11-03-permissions-design.md`.

## 6.1 Purpose

Without permissions, a generated policy lets every signed-in user act on every record. With `make:crud --permissions` each policy method checks a named permission, and a permission no role holds is denied. A new entity is locked until an app grants access.

The package does not pick an app's roles. It supplies names, one definitions file, a sync command, a super-admin, optional ownership and tests. Each app fills in its own roles.

Permissions need `spatie/laravel-permission` (`composer require spatie/laravel-permission`). Without it, `engineering:install --permissions` and `make:crud --permissions` refuse and print that command; everything else works.

## 6.2 Setup

```bash
composer require spatie/laravel-permission
php artisan engineering:install --permissions     # combinable with --profile
php artisan migrate
```

Re-running is safe. The command:

- creates `app/Shared/Permissions/Permissions.php` (config `blueprint.permissions`; never overwrites it);
- creates spatie's migration (`database/migrations`); in `tenant-saas` also `database/migrations/tenant`, plus `app/Shared/Permissions/CentralPermissions.php` (config `blueprint.central_permissions`);
- adds `HasRoles` to `App\Models\User`, or prints the line to add when the file cannot be edited safely.

Config keys: `permissions`, `central_permissions`, `super_admin` (default `super-admin`), `permission_guard` (default `web`).

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
- `tenant-saas`: no option syncs `Permissions` into all tenants; `--tenant=<id>` syncs one tenant; `--central` syncs `CentralPermissions` into the central database. `--central` and `--tenant` cannot be combined. `tenant:create` has a "Sync permissions" step.

## 6.6 Super-admin

The package registers a `Gate::before` that returns `true` for users with the `blueprint.super_admin` role and `null` otherwise (it never returns `false`, so it cannot block another check). `permissions:sync` creates the role; assigning it is the app's job:

```php
$user->assignRole(config('blueprint.super_admin'));
```

## 6.7 `make:crud --permissions`

Refuses when permissions are not set up. Otherwise it:

- adds the entity's group (`view`, `create`, `update`, `delete`) to `groups()` in `Permissions` (`CentralPermissions` for a `--central` module);
- generates a policy where `viewAny` and `view` check `<group>.view`, and `create`, `update`, `delete` check their own permission. Guests are always denied;
- adds generated tests that sync the permissions and build their own test role, so they pass with no edit to `roles()`;
- prints the next step: add the group to a role and run `permissions:sync`.

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

- spatie's tables exist in each tenant database and in the central database. Because users are already per database, spatie's `teams` mode is not used.
- The permission cache is isolated per tenant: the cache key becomes `<cache key>.tenant.<id>` while a tenant is active, and spatie's in-memory permissions are reset when tenancy starts and ends. This holds with or without stancl's cache bootstrapper.

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
