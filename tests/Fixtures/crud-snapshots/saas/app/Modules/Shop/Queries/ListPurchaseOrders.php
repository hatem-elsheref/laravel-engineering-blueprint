<?php

declare(strict_types=1);

namespace App\Modules\Shop\Queries;

use App\Modules\Shop\Models\PurchaseOrder;
use Best\Blueprint\Support\ListPerPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListPurchaseOrders
{
    /**
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function handle(?int $perPage = null): LengthAwarePaginator
    {
        $query = PurchaseOrder::query();
        return $query->latest('id')->paginate(ListPerPage::resolve($perPage));
    }
}
