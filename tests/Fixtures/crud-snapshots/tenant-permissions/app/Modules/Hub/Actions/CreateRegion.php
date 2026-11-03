<?php

declare(strict_types=1);

namespace App\Modules\Hub\Actions;

use App\Modules\Hub\Data\RegionData;
use App\Modules\Hub\Models\Region;
use Illuminate\Support\Facades\DB;

final class CreateRegion
{
    public function handle(RegionData $data): Region
    {
        return DB::transaction(fn (): Region => Region::create($data->toArray()));
    }
}
