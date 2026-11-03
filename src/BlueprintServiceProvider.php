<?php

namespace Best\Blueprint;

use Best\Blueprint\Support\Compatibility;
use Best\Blueprint\Tenancy\TenancyDetector;
use Illuminate\Support\ServiceProvider;

class BlueprintServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/blueprint.php', 'blueprint');

        $this->app->singleton(Compatibility::class, fn ($app) => new Compatibility(
            (string) ($app['config']->get('blueprint.support.php') ?: '8.3'),
            (string) ($app['config']->get('blueprint.support.laravel') ?: '12'),
        ));
    }

    public function boot(): void
    {
        if ($this->app->make(TenancyDetector::class)->active()) {
            $this->registerTenancyMiddleware();
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/blueprint.php' => config_path('blueprint.php'),
        ], 'blueprint-config');

        $stubs = [];

        foreach (glob(__DIR__.'/../stubs/*.stub') ?: [] as $stub) {
            $stubs[$stub] = base_path('stubs/blueprint/'.basename($stub));
        }

        $this->publishes($stubs, 'blueprint-stubs');

        $this->commands([
            Console\AboutCommand::class,
            Console\InstallCommand::class,
            Console\MakeModuleCommand::class,
            Console\MakeDtoCommand::class,
            Console\MakeActionCommand::class,
            Console\MakeCrudCommand::class,
            Console\TenantCreateCommand::class,
        ]);
    }

    /**
     * The "tenant" and "central" route middleware groups. Tenancy middleware goes to the top of the
     * priority list (as stancl/tenancy's own provider stub does) so tenants are identified before
     * route-model binding and central-domain requests get a 404, not an exception.
     */
    private function registerTenancyMiddleware(): void
    {
        $router = $this->app['router'];

        $router->middlewareGroup('tenant', [
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
        ]);
        $router->middlewareGroup('central', [Http\Middleware\CentralDomainsOnly::class]);

        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);

        foreach ([\Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class, \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class] as $middleware) {
            $kernel->prependToMiddlewarePriority($middleware);
        }
    }
}
