<?php

declare(strict_types=1);

namespace App\Modules\Orders\Controllers;

use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Actions\DeleteOrder;
use App\Modules\Orders\Actions\UpdateOrder;
use App\Modules\Orders\Data\OrderData;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Queries\ListOrders;
use App\Modules\Orders\Requests\StoreOrderRequest;
use App\Modules\Orders\Requests\UpdateOrderRequest;
use App\Modules\Orders\Resources\OrderResource;
use Best\Blueprint\Support\ListPerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class OrderController
{
    public function index(Request $request, ListOrders $query): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Order::class);

        return OrderResource::collection($query->handle(ListPerPage::fromRequest($request)));
    }

    public function store(StoreOrderRequest $request, CreateOrder $action): JsonResponse
    {
        $order = $action->handle(OrderData::fromRequest($request));

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order);
    }

    public function update(UpdateOrderRequest $request, Order $order, UpdateOrder $action): OrderResource
    {
        return new OrderResource($action->handle($order, OrderData::fromRequest($request)));
    }

    public function destroy(Order $order, DeleteOrder $action): Response
    {
        Gate::authorize('delete', $order);

        $action->handle($order);

        return response()->noContent();
    }
}
