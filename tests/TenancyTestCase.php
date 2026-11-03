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

    protected string $centralDatabase;

    protected function defineEnvironment($app): void
    {
        // Central database in a temp file (not :memory:), so tenant connections can never share it.
        $this->centralDatabase = sys_get_temp_dir().'/leb-central-'.uniqid().'.sqlite';
        touch($this->centralDatabase);
        $app['config']->set('database.connections.testing.database', $this->centralDatabase);

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

    protected function setUp(): void
    {
        parent::setUp();

        // What the app's published App\Providers\TenancyServiceProvider wires up: switch to the tenant's
        // database when tenancy starts and back when it ends.
        \Illuminate\Support\Facades\Event::listen(\Stancl\Tenancy\Events\TenancyInitialized::class, \Stancl\Tenancy\Listeners\BootstrapTenancy::class);
        \Illuminate\Support\Facades\Event::listen(\Stancl\Tenancy\Events\TenancyEnded::class, \Stancl\Tenancy\Listeners\RevertToCentralContext::class);
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

        $central = $this->centralDatabase ?? null;

        parent::tearDown();

        if ($central !== null && is_file($central)) {
            unlink($central);
        }
    }
}
