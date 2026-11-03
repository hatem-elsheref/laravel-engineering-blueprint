<?php

declare(strict_types=1);

use App\Modules\Shop\Controllers\PurchaseOrderPageController;
use Illuminate\Support\Facades\Route;

// Loaded with the "web" middleware group.
// Route::get('/shop', IndexController::class);

Route::resource('purchase-orders', PurchaseOrderPageController::class)
    ->missing(fn ($request) => abort($request->user() === null ? 403 : 404));
