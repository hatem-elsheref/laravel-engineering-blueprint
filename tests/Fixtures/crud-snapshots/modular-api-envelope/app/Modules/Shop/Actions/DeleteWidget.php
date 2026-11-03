<?php

declare(strict_types=1);

namespace App\Modules\Shop\Actions;

use App\Modules\Shop\Models\Widget;
use Illuminate\Support\Facades\DB;

final class DeleteWidget
{
    public function handle(Widget $widget): void
    {
        DB::transaction(fn () => $widget->delete());
    }
}
