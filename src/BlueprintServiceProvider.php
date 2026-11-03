<?php

namespace Best\Blueprint;

use Best\Blueprint\Support\Compatibility;
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
        ]);
    }
}
