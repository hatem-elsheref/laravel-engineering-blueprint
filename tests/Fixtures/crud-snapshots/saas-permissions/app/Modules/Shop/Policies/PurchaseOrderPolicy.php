<?php

declare(strict_types=1);

namespace App\Modules\Shop\Policies;

use App\Models\User;
use App\Modules\Shop\Models\PurchaseOrder;

/**
 * Each ability needs a named permission (purchase-orders.view, .create, .update, .delete). Grant them to roles in
 * Permissions::roles() and run permissions:sync. Guests are always denied: every method requires a User.
 */
final class PurchaseOrderPolicy
{
    private const GROUP = 'purchase-orders';

    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(self::GROUP.'.view');
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->checkPermissionTo(self::GROUP.'.view');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(self::GROUP.'.create');
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->checkPermissionTo(self::GROUP.'.update');
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->checkPermissionTo(self::GROUP.'.delete');
    }
}
