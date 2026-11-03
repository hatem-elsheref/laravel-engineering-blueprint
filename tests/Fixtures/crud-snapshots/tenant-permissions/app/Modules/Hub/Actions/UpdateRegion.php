<?php

declare(strict_types=1);

namespace App\Modules\Hub\Actions;

use App\Modules\Hub\Data\RegionData;
use App\Modules\Hub\Models\Region;
use Illuminate\Support\Facades\DB;

final class UpdateRegion
{
    public function handle(Region $region, RegionData $data): Region
    {
        return DB::transaction(function () use ($region, $data): Region {
            $region->update($data->toArray());

            return $region->refresh();
        });
    }
}
