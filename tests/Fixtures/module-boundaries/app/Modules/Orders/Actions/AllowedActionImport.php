<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Inventory\Actions\ReserveStock;

final class AllowedActionImport
{
    public function run(ReserveStock $reserve): void {}
}
