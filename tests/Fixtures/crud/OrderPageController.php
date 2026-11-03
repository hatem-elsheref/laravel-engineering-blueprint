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
use Best\Blueprint\Support\ListPerPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class OrderPageController
{
    public function index(Request $request, ListOrders $query): View
    {
        Gate::authorize('viewAny', Order::class);

        return view('orders.orders.index', ['orders' => $query->handle(ListPerPage::fromRequest($request))]);
    }

    public function create(): View
    {
        Gate::authorize('create', Order::class);

        return view('orders.orders.create', ['order' => new Order]);
    }

    public function store(StoreOrderRequest $request, CreateOrder $action): RedirectResponse
    {
        $order = $action->handle(OrderData::fromRequest($request));

        return redirect()->route('orders.show', $order)->with('status', __(':item created.', ['item' => __('Order')]));
    }

    public function show(Order $order): View
    {
        Gate::authorize('view', $order);

        return view('orders.orders.show', ['order' => $order]);
    }

    public function edit(Order $order): View
    {
        Gate::authorize('update', $order);

        return view('orders.orders.edit', ['order' => $order]);
    }

    public function update(UpdateOrderRequest $request, Order $order, UpdateOrder $action): RedirectResponse
    {
        $order = $action->handle($order, OrderData::fromRequest($request));

        return redirect()->route('orders.show', $order)->with('status', __(':item updated.', ['item' => __('Order')]));
    }

    public function destroy(Order $order, DeleteOrder $action): RedirectResponse
    {
        Gate::authorize('delete', $order);

        $action->handle($order);

        return redirect()->route('orders.index')->with('status', __(':item deleted.', ['item' => __('Order')]));
    }
}
