<?php

namespace Best\Blueprint\Tests;

use Best\Blueprint\BlueprintServiceProvider;
use Stancl\Tenancy\TenancyServiceProvider;

/**
 * Boots stancl/tenancy with a tenant-saas profile; tenant databases are sqlite files in database_path().
 */
abstract class TenancyTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        require_once __DIR__.'/Fixtures/Tenant.php';
        require_once __DIR__.'/Fixtures/User.php';
        require_once __DIR__.'/Fixtures/UserFactory.php';

        return [TenancyServiceProvider::class, BlueprintServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('blueprint.profile', 'tenant-saas');
        $app['config']->set('tenancy.tenant_model', \App\Models\Tenant::class);
        $app['config']->set('tenancy.central_domains', ['localhost']);
        $app['config']->set('tenancy.database.central_connection', 'testing');
        $app['config']->set('tenancy.database.prefix', 'tenant_');
        $app['config']->set('tenancy.bootstrappers', [
            \Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
            \Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../vendor/stancl/tenancy/assets/migrations');
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && $this->app && tenancy()->initialized) {
            tenancy()->end();
        }

        foreach (glob(database_path('tenant_*')) ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }
}
