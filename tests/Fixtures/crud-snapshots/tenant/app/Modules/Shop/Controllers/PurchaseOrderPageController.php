<?php

declare(strict_types=1);

namespace App\Modules\Shop\Controllers;

use App\Modules\Shop\Actions\CreatePurchaseOrder;
use App\Modules\Shop\Actions\DeletePurchaseOrder;
use App\Modules\Shop\Actions\UpdatePurchaseOrder;
use App\Modules\Shop\Data\PurchaseOrderData;
use App\Modules\Shop\Models\PurchaseOrder;
use App\Modules\Shop\Queries\ListPurchaseOrders;
use App\Modules\Shop\Requests\StorePurchaseOrderRequest;
use App\Modules\Shop\Requests\UpdatePurchaseOrderRequest;
use Best\Blueprint\Support\ListPerPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class PurchaseOrderPageController
{
    public function index(Request $request, ListPurchaseOrders $query): View
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        return view('shop.purchase-orders.index', ['purchaseOrders' => $query->handle(ListPerPage::fromRequest($request))]);
    }

    public function create(): View
    {
        Gate::authorize('create', PurchaseOrder::class);

        return view('shop.purchase-orders.create', ['purchaseOrder' => new PurchaseOrder]);
    }

    public function store(StorePurchaseOrderRequest $request, CreatePurchaseOrder $action): RedirectResponse
    {
        $purchaseOrder = $action->handle(PurchaseOrderData::fromRequest($request));

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('status', __(':item created.', ['item' => __('Purchase Order')]));
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        Gate::authorize('view', $purchaseOrder);

        return view('shop.purchase-orders.show', ['purchaseOrder' => $purchaseOrder]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        Gate::authorize('update', $purchaseOrder);

        return view('shop.purchase-orders.edit', ['purchaseOrder' => $purchaseOrder]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, UpdatePurchaseOrder $action): RedirectResponse
    {
        $purchaseOrder = $action->handle($purchaseOrder, PurchaseOrderData::fromRequest($request));

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('status', __(':item updated.', ['item' => __('Purchase Order')]));
    }

    public function destroy(PurchaseOrder $purchaseOrder, DeletePurchaseOrder $action): RedirectResponse
    {
        Gate::authorize('delete', $purchaseOrder);

        $action->handle($purchaseOrder);

        return redirect()->route('purchase-orders.index')->with('status', __(':item deleted.', ['item' => __('Purchase Order')]));
    }
}
