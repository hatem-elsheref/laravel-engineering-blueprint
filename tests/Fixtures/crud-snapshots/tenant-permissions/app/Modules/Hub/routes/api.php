<?php

declare(strict_types=1);

use App\Modules\Hub\Controllers\RegionController;
use Illuminate\Support\Facades\Route;

// Loaded with the "api" middleware group and the /api prefix.
// Route::get('/hub', ListController::class);

Route::apiResource('regions', RegionController::class)->names('api.regions')
    ->missing(fn ($request) => abort($request->user() === null ? 403 : 404));
