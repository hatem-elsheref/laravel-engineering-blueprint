<?php

declare(strict_types=1);

namespace App\Modules\Hub;

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

        $domains = implode('|', array_map('preg_quote', config('tenancy.central_domains', [])));

        Route::domain('{central_domain}')->where(['central_domain' => $domains])->middleware(['api', 'central'])->prefix('api')->name('central.')->group(__DIR__.'/routes/api.php');
    }
}
