<?php

namespace Best\Blueprint\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * For tests of permission-protected code, on whichever database is current (central or tenant).
 * Needs spatie/laravel-permission.
 *
 * grantPermissions() REPLACES all the user's roles (a super-admin role set earlier included) with one test
 * role, so the user ends up with exactly the given permissions. makeSuperAdmin() is additive.
 */
trait InteractsWithPermissions
{
    protected function grantPermissions(Authenticatable $user, string ...$permissions): void
    {
        $guard = $this->permissionTestGuard();
        $names = array_values(array_unique($permissions));
        sort($names);

        $this->forgetPermissionCache();

        foreach ($names as $name) {
            Permission::findOrCreate($name, $guard);
        }

        $role = Role::findOrCreate('test-role-'.substr(sha1(implode('|', $names)), 0, 8), $guard);
        $role->syncPermissions($names);

        $this->assignTestRole($user, $role, replace: true);
    }

    protected function makeSuperAdmin(Authenticatable $user): void
    {
        $this->forgetPermissionCache();

        $role = Role::findOrCreate((string) config('blueprint.super_admin', 'super-admin'), $this->permissionTestGuard());

        $this->assignTestRole($user, $role);
    }

    private function assignTestRole(Authenticatable $user, Role $role, bool $replace = false): void
    {
        $replace ? $user->syncRoles([$role]) : $user->assignRole($role);

        // Drop what the user model and spatie memoised, so the next check reads the new grants.
        if (method_exists($user, 'unsetRelation')) {
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
        $this->forgetPermissionCache();
    }

    private function permissionTestGuard(): string
    {
        return (string) config('blueprint.permission_guard', 'web');
    }

    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
