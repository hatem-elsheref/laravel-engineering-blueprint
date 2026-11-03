<?php

declare(strict_types=1);

namespace App\Modules\Shop\Actions;

use App\Modules\Shop\Data\PurchaseOrderData;
use App\Modules\Shop\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

final class UpdatePurchaseOrder
{
    public function handle(PurchaseOrder $purchaseOrder, PurchaseOrderData $data): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $data): PurchaseOrder {
            $purchaseOrder->update($data->toArray());

            return $purchaseOrder->refresh();
        });
    }
}
