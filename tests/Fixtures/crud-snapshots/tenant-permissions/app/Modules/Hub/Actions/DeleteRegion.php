<?php

declare(strict_types=1);

namespace App\Modules\Hub\Actions;

use App\Modules\Hub\Models\Region;
use Illuminate\Support\Facades\DB;

final class DeleteRegion
{
    public function handle(Region $region): void
    {
        DB::transaction(fn () => $region->delete());
    }
}
