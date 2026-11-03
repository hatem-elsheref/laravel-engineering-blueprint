<?php

declare(strict_types=1);

use App\Modules\Shop\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;

// Loaded with the "api" middleware group and the /api prefix.
// Route::get('/shop', ListController::class);

Route::apiResource('widgets', WidgetController::class)->names('api.widgets')
    ->middleware('auth:sanctum')
    ->missing(fn ($request) => abort($request->user() === null ? 403 : 404));
