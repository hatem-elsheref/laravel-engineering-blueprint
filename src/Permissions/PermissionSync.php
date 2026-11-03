<?php

namespace Best\Blueprint\Permissions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Writes a PermissionSet into the current default connection, with the permission and role models spatie is
 * configured with. Only call it after PermissionsDetector::spatie().
 */
class PermissionSync
{
    /** Whether the current default connection has spatie's permissions table (its configured name). */
    public static function tablesExist(): bool
    {
        return Schema::hasTable((string) config('permission.table_names.permissions', 'permissions'));
    }

    public function run(PermissionSet $set, bool $prune): SyncResult
    {
        $guard = (string) config('blueprint.permission_guard', 'web');
        $superAdmin = (string) config('blueprint.super_admin', 'super-admin');
        $registrar = app(PermissionRegistrar::class);
        /** @var class-string<Permission> $permissionModel */
        $permissionModel = $registrar->getPermissionClass();
        /** @var class-string<Role> $roleModel */
        $roleModel = $registrar->getRoleClass();

        $result = DB::transaction(function () use ($set, $prune, $guard, $superAdmin, $permissionModel, $roleModel): SyncResult {
            $permissionsAdded = 0;
            $rolesAdded = 0;

            foreach ($set->permissions() as $name) {
                $permission = $permissionModel::findOrCreate($name, $guard);
                $permissionsAdded += $permission->wasRecentlyCreated ? 1 : 0;
            }

            foreach ($set->roles() as $name => $permissions) {
                $role = $roleModel::findOrCreate($name, $guard);
                $rolesAdded += $role->wasRecentlyCreated ? 1 : 0;
                $role->syncPermissions($permissions);
            }

            $rolesAdded += $roleModel::findOrCreate($superAdmin, $guard)->wasRecentlyCreated ? 1 : 0;

            $stalePermissions = $permissionModel::query()->where('guard_name', $guard)
                ->whereNotIn('name', $set->permissions())->pluck('name')->all();
            $staleRoles = $roleModel::query()->where('guard_name', $guard)
                ->whereNotIn('name', [...array_keys($set->roles()), $superAdmin])->pluck('name')->all();

            if ($prune) {
                $permissionModel::query()->where('guard_name', $guard)->whereIn('name', $stalePermissions)->get()->each->delete();
                $roleModel::query()->where('guard_name', $guard)->whereIn('name', $staleRoles)->get()->each->delete();
            }

            return new SyncResult(
                $permissionsAdded,
                $rolesAdded,
                [...array_map('strval', $stalePermissions), ...array_map(fn ($r) => 'role:'.$r, $staleRoles)],
                $prune,
            );
        });

        $registrar->forgetCachedPermissions();

        return $result;
    }
}
