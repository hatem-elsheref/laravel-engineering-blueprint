<?php

declare(strict_types=1);

namespace App\Modules\Shop\Actions;

use App\Modules\Shop\Data\WidgetData;
use App\Modules\Shop\Models\Widget;
use Illuminate\Support\Facades\DB;

final class UpdateWidget
{
    public function handle(Widget $widget, WidgetData $data): Widget
    {
        return DB::transaction(function () use ($widget, $data): Widget {
            $widget->update($data->toArray());

            return $widget->refresh();
        });
    }
}
