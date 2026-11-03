<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class Product extends Model
{
    /** @return Builder<static> */
    public static function query(): Builder
    {
        return parent::query();
    }
}
