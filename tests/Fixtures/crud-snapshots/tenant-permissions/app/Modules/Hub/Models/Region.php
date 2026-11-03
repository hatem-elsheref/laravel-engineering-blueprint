<?php

declare(strict_types=1);

namespace App\Modules\Hub\Models;

use Database\Factories\Hub\RegionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    protected static function newFactory(): RegionFactory
    {
        return RegionFactory::new();
    }
}
