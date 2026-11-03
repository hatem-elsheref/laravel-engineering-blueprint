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
use App\Modules\Shop\Resources\PurchaseOrderResource;
use Best\Blueprint\Support\ListPerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class PurchaseOrderController
{
    public function index(Request $request, ListPurchaseOrders $query): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        return PurchaseOrderResource::collection($query->handle(ListPerPage::fromRequest($request)));
    }

    public function store(StorePurchaseOrderRequest $request, CreatePurchaseOrder $action): JsonResponse
    {
        $purchaseOrder = $action->handle(PurchaseOrderData::fromRequest($request));

        return (new PurchaseOrderResource($purchaseOrder))->response()->setStatusCode(201);
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        Gate::authorize('view', $purchaseOrder);

        return new PurchaseOrderResource($purchaseOrder);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, UpdatePurchaseOrder $action): PurchaseOrderResource
    {
        return new PurchaseOrderResource($action->handle($purchaseOrder, PurchaseOrderData::fromRequest($request)));
    }

    public function destroy(PurchaseOrder $purchaseOrder, DeletePurchaseOrder $action): Response
    {
        Gate::authorize('delete', $purchaseOrder);

        $action->handle($purchaseOrder);

        return response()->noContent();
    }
}
