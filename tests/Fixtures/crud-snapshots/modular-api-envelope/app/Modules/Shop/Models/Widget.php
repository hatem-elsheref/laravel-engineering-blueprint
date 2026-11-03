<?php

declare(strict_types=1);

namespace App\Modules\Shop\Models;

use App\Models\User;
use Database\Factories\Shop\WidgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Widget extends Model
{
    /** @use HasFactory<WidgetFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
    ];
/**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    protected static function newFactory(): WidgetFactory
    {
        return WidgetFactory::new();
    }
}
