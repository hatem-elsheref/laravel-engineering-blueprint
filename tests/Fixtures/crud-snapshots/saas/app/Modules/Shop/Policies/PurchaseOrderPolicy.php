<?php

declare(strict_types=1);

namespace App\Modules\Shop\Policies;

use App\Models\User;
use App\Modules\Shop\Models\PurchaseOrder;

/**
 * Guests are always denied: every method requires a User.
 */
final class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        // Tighten for your app.
        return true;
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        // Tighten for your app.
        return true;
    }

    public function create(User $user): bool
    {
        // Tighten for your app.
        return true;
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        // Tighten for your app.
        return true;
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        // Tighten for your app.
        return true;
    }
}
