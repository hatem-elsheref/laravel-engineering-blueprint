<?php

declare(strict_types=1);

namespace App\Modules\Shop\Actions;

use App\Models\User;
use App\Modules\Shop\Data\WidgetData;
use App\Modules\Shop\Models\Widget;
use Illuminate\Support\Facades\DB;

final class CreateWidget
{
    /**
     * The owner is the signed-in user, never request input.
     */
    public function handle(WidgetData $data, User $owner): Widget
    {
        return DB::transaction(function () use ($data, $owner): Widget {
            $widget = new Widget($data->toArray());
            $widget->user()->associate($owner);
            $widget->save();

            return $widget;
        });
    }
}
