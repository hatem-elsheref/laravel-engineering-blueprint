<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Inventory\Models\Product;

final class CrossModuleUse
{
    public function __construct(private readonly Product $product) {}
}
