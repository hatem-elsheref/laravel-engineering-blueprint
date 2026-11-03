<?php

declare(strict_types=1);

namespace App\Modules\Hub\Policies;

use App\Models\User;
use App\Modules\Hub\Models\Region;

/**
 * Each ability needs a named permission (regions.view, .create, .update, .delete). Grant them to roles in
 * CentralPermissions::roles() and run permissions:sync --central. Guests are always denied: every method requires a User.
 */
final class RegionPolicy
{
    private const GROUP = 'regions';

    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(self::GROUP.'.view');
    }

    public function view(User $user, Region $region): bool
    {
        return $user->checkPermissionTo(self::GROUP.'.view');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(self::GROUP.'.create');
    }

    public function update(User $user, Region $region): bool
    {
        return $user->checkPermissionTo(self::GROUP.'.update');
    }

    public function delete(User $user, Region $region): bool
    {
        return $user->checkPermissionTo(self::GROUP.'.delete');
    }
}
