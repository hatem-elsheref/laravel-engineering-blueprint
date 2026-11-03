<?php

declare(strict_types=1);

namespace App\Modules\Shop\Queries;

use App\Modules\Shop\Models\PurchaseOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListPurchaseOrders
{
    /**
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function handle(int $perPage = 15): LengthAwarePaginator
    {
        return PurchaseOrder::query()->latest('id')->paginate($perPage);
    }
}
