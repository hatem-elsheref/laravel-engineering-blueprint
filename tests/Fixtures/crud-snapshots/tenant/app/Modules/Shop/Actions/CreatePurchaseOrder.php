<?php

declare(strict_types=1);

namespace App\Modules\Shop\Actions;

use App\Modules\Shop\Data\PurchaseOrderData;
use App\Modules\Shop\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

final class CreatePurchaseOrder
{
    public function handle(PurchaseOrderData $data): PurchaseOrder
    {
        return DB::transaction(fn (): PurchaseOrder => PurchaseOrder::create($data->toArray()));
    }
}
