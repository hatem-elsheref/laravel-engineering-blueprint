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

        // One request or one queued job; Laravel resets scoped instances between them.
        $this->app->scoped(Features\FeatureManager::class);

        $this->app->singleton(Compatibility::class, fn ($app) => new Compatibility(
            (string) ($app['config']->get('blueprint.support.php') ?: '8.3'),
            (string) ($app['config']->get('blueprint.support.laravel') ?: '12'),
        ));
    }

    public function boot(): void
    {
        if ($this->app->make(TenancyDetector::class)->active()) {
            $this->registerTenancyMiddleware();

            \Illuminate\Support\Facades\Event::listen(
                [\Stancl\Tenancy\Events\TenancyInitialized::class, \Stancl\Tenancy\Events\TenancyEnded::class],
                fn () => $this->app->resolved(Features\FeatureManager::class) ? $this->app->make(Features\FeatureManager::class)->forget() : null,
            );
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

        // Unknown tenant domains are "not found", not a server error.
        \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::$onFail = fn () => abort(404);

        // route() for central-module routes (bound to Route::domain('{central_domain}')); the
        // CentralDomainsOnly middleware overrides this with the request's host.
        $central = (array) config('tenancy.central_domains', []);
        \Illuminate\Support\Facades\URL::defaults(['central_domain' => $central[0] ?? 'localhost']);

        // Laravel's database queue uses the *default* connection, which is the tenant's database while
        // tenancy is active; jobs must go to the central database where the worker reads them.
        if (config('queue.connections.database.connection') === null) {
            config()->set('queue.connections.database.connection', config('tenancy.database.central_connection'));
        }


        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);

        foreach ([\Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class, \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class] as $middleware) {
            $kernel->prependToMiddlewarePriority($middleware);
        }
    }
}
