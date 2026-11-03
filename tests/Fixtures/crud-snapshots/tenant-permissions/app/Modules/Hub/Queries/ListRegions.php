<?php

declare(strict_types=1);

namespace App\Modules\Hub\Queries;

use App\Modules\Hub\Models\Region;
use Best\Blueprint\Support\ListPerPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListRegions
{
    /**
     * @return LengthAwarePaginator<int, Region>
     */
    public function handle(?int $perPage = null): LengthAwarePaginator
    {
        $query = Region::query();
        return $query->latest('id')->paginate(ListPerPage::resolve($perPage));
    }
}
