<?php

namespace Best\Blueprint\Permissions;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Writes a PermissionSet into the current default connection. Only call it after PermissionsDetector::spatie().
 */
class PermissionSync
{
    public function run(PermissionSet $set, bool $prune): SyncResult
    {
        $guard = (string) config('blueprint.permission_guard', 'web');
        $superAdmin = (string) config('blueprint.super_admin', 'super-admin');

        $result = DB::transaction(function () use ($set, $prune, $guard, $superAdmin): SyncResult {
            $permissionsAdded = 0;
            $rolesAdded = 0;

            foreach ($set->permissions() as $name) {
                $permission = Permission::findOrCreate($name, $guard);
                $permissionsAdded += $permission->wasRecentlyCreated ? 1 : 0;
            }

            foreach ($set->roles() as $name => $permissions) {
                $role = Role::findOrCreate($name, $guard);
                $rolesAdded += $role->wasRecentlyCreated ? 1 : 0;
                $role->syncPermissions($permissions);
            }

            $rolesAdded += Role::findOrCreate($superAdmin, $guard)->wasRecentlyCreated ? 1 : 0;

            $stalePermissions = Permission::query()->where('guard_name', $guard)
                ->whereNotIn('name', $set->permissions())->pluck('name')->all();
            $staleRoles = Role::query()->where('guard_name', $guard)
                ->whereNotIn('name', [...array_keys($set->roles()), $superAdmin])->pluck('name')->all();

            if ($prune) {
                Permission::query()->where('guard_name', $guard)->whereIn('name', $stalePermissions)->get()->each->delete();
                Role::query()->where('guard_name', $guard)->whereIn('name', $staleRoles)->get()->each->delete();
            }

            return new SyncResult(
                $permissionsAdded,
                $rolesAdded,
                [...array_map('strval', $stalePermissions), ...array_map(fn ($r) => 'role:'.$r, $staleRoles)],
                $prune,
            );
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $result;
    }
}
