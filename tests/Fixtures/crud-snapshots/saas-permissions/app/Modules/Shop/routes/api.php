<?php

declare(strict_types=1);

use App\Modules\Shop\Controllers\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

// Loaded with the "api" middleware group and the /api prefix.
// Route::get('/shop', ListController::class);

Route::apiResource('purchase-orders', PurchaseOrderController::class)->names('api.purchase-orders')
    ->missing(fn ($request) => abort($request->user() === null ? 403 : 404));
