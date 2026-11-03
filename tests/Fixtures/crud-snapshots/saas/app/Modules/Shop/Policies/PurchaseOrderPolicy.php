<?php

declare(strict_types=1);

namespace App\Modules\Shop\Policies;

use App\Models\User;
use App\Modules\Shop\Models\PurchaseOrder;

/**
 * Blueprint: deny-by-default policy.
 *
 * Guests are always denied: every method requires a User.
 * Authenticated users are denied until you implement authorization below,
 * or regenerate with make:crud --permissions / use --open-policy for local demos only.
 */
final class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return false;
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return false;
    }
}
