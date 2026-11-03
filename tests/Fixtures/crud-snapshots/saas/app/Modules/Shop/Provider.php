<?php

declare(strict_types=1);

namespace App\Modules\Shop;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class Provider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        // Generated API routes use the 'api' middleware only — add auth (e.g. auth:sanctum) before production (docs/SECURITY-GENERATION.md).

        Route::middleware('web')->group(__DIR__.'/routes/web.php');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/routes/api.php');
    }
}
