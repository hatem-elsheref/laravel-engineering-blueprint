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

        Route::middleware(['api', 'auth:sanctum'])->prefix('api')->group(__DIR__.'/routes/api.php');
    }
}
