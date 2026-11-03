<?php

declare(strict_types=1);

namespace App\Modules\Shop\Controllers;

use App\Modules\Shop\Actions\CreateWidget;
use App\Modules\Shop\Actions\DeleteWidget;
use App\Modules\Shop\Actions\UpdateWidget;
use App\Modules\Shop\Data\WidgetData;
use App\Modules\Shop\Models\Widget;
use App\Modules\Shop\Queries\ListWidgets;
use App\Modules\Shop\Requests\StoreWidgetRequest;
use App\Modules\Shop\Requests\UpdateWidgetRequest;
use App\Modules\Shop\Resources\WidgetResource;
use Best\Blueprint\Http\ApiResponse;
use Best\Blueprint\Support\ListPerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class WidgetController
{
    public function index(Request $request, ListWidgets $query): JsonResponse
    {
        Gate::authorize('viewAny', Widget::class);

        return ApiResponse::collection(WidgetResource::collection($query->handle($request->user(), ListPerPage::fromRequest($request))));
    }

    public function store(StoreWidgetRequest $request, CreateWidget $action): JsonResponse
    {
        $widget = $action->handle(WidgetData::fromRequest($request), $request->user());

        return ApiResponse::created(new WidgetResource($widget));
    }

    public function show(Widget $widget): JsonResponse
    {
        Gate::authorize('view', $widget);

        return ApiResponse::resource(new WidgetResource($widget));
    }

    public function update(UpdateWidgetRequest $request, Widget $widget, UpdateWidget $action): JsonResponse
    {
        return ApiResponse::resource(new WidgetResource($action->handle($widget, WidgetData::fromRequest($request))));
    }

    public function destroy(Widget $widget, DeleteWidget $action): Response
    {
        Gate::authorize('delete', $widget);

        $action->handle($widget);

        return response()->noContent();
    }
}
