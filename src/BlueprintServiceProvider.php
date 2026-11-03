<?php

namespace Best\Blueprint;

use Best\Blueprint\Support\Compatibility;
use Best\Blueprint\Tenancy\TenancyDetector;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class BlueprintServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/blueprint.php', 'blueprint');

        // One request or one queued job; Laravel resets scoped instances between them.
        $this->app->singleton(Flags\HttpDispatch::class);

        // The app's own default language; setLocale() overwrites config('app.locale') at runtime.
        $this->app->instance('blueprint.default_locale', (string) $this->app['config']->get('app.locale', 'en'));
        $this->app->scoped(Features\FeatureManager::class);
        $this->app->scoped(Settings\SettingManager::class);

        $this->app->singleton(Compatibility::class, fn ($app) => new Compatibility(
            (string) ($app['config']->get('blueprint.support.php') ?: '8.3'),
            (string) ($app['config']->get('blueprint.support.laravel') ?: '12'),
        ));
    }

    public function boot(): void
    {
        $this->app['router']->aliasMiddleware('feature', Http\Middleware\EnsureFeatureEnabled::class);

        // Interface translations: package strings, the language switch route, per-request locale.
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        // Laravel's own "This action is unauthorized." is not translated; translate it per request language.
        $this->callAfterResolving(ExceptionHandler::class, function (mixed $handler): void {
            if ($handler instanceof Handler) {
                $handler->map(
                    AuthorizationException::class,
                    fn (AuthorizationException $e) => $e->getMessage() === 'This action is unauthorized.'
                        ? (new AuthorizationException(__('This action is unauthorized.'), $e->getCode(), $e))->withStatus($e->status())
                        : $e,
                );
            }
        });

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $kernel = $this->app->make(Kernel::class);
        $kernel->appendMiddlewareToGroup('web', Http\Middleware\SetLocale::class);
        $kernel->appendMiddlewareToGroup('api', Http\Middleware\SetLocale::class);

        // Admin writes are refused while a route is being handled, also in an app's own HTTP tests.
        Event::listen(RouteMatched::class, fn () => $this->app->make(Flags\HttpDispatch::class)->active = true);
        Event::listen(RequestHandled::class, fn () => $this->app->make(Flags\HttpDispatch::class)->active = false);

        if ($this->app->bound('blade.compiler')) {
            Blade::if('feature', fn (string $key): bool => $this->app->make(Features\FeatureManager::class)->enabled($key));
        }

        // The super-admin role passes every check. Returns true or null, never false, so it can't block anything.
        if ($this->app->make(Permissions\PermissionsDetector::class)->spatie()) {
            Gate::before(function (mixed $user): ?bool {
                if (is_object($user) && method_exists($user, 'hasRole')
                    && $user->hasRole((string) config('blueprint.super_admin', 'super-admin'), (string) config('blueprint.permission_guard', 'web'))) {
                    return true;
                }

                return null;
            });
        }

        if ($this->app->make(TenancyDetector::class)->active()) {
            $this->registerTenancyMiddleware();

            Event::listen(
                [TenancyInitialized::class, TenancyEnded::class],
                function (): void {
                    foreach ([Features\FeatureManager::class, Settings\SettingManager::class] as $manager) {
                        if ($this->app->resolved($manager)) {
                            $this->app->make($manager)->forget();
                        }
                    }
                },
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
            Console\FeatureListCommand::class,
            Console\FeatureEnableCommand::class,
            Console\FeatureDisableCommand::class,
            Console\FeaturesSyncCommand::class,
            Console\PermissionsSyncCommand::class,
            Console\SettingListCommand::class,
            Console\SettingSetCommand::class,
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
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
        $router->middlewareGroup('central', [Http\Middleware\CentralDomainsOnly::class]);

        // Every web session is tied to the tenant (or central app) where it started.
        $this->app->make(Kernel::class)->appendMiddlewareToGroup('web', Http\Middleware\ScopeSessionToDomain::class);

        // Unknown tenant domains are "not found", not a server error.
        InitializeTenancyByDomain::$onFail = fn () => abort(404);

        // route() for central-module routes (bound to Route::domain('{central_domain}')); the
        // CentralDomainsOnly middleware overrides this with the request's host.
        $central = (array) config('tenancy.central_domains', []);
        URL::defaults(['central_domain' => $central[0] ?? 'localhost']);

        // Laravel's database queue uses the *default* connection, which is the tenant's database while
        // tenancy is active; jobs must go to the central database where the worker reads them.
        if (config('queue.connections.database.connection') === null) {
            config()->set('queue.connections.database.connection', config('tenancy.database.central_connection'));
        }

        $kernel = $this->app->make(Kernel::class);

        foreach ([InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class] as $middleware) {
            $kernel->prependToMiddlewarePriority($middleware);
        }
    }
}
