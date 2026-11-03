<?php

declare(strict_types=1);

namespace App\Modules\Shop\Actions;

use App\Modules\Shop\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

final class DeletePurchaseOrder
{
    public function handle(PurchaseOrder $purchaseOrder): void
    {
        DB::transaction(fn () => $purchaseOrder->delete());
    }
}
