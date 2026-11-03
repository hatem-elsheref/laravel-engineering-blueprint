<?php

declare(strict_types=1);

namespace Best\Blueprint\Tests\MysqlIntegration;

use App\Models\Tenant;
use Best\Blueprint\BlueprintServiceProvider;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;
use Stancl\Tenancy\Listeners\BootstrapTenancy;
use Stancl\Tenancy\Listeners\RevertToCentralContext;
use Stancl\Tenancy\TenancyServiceProvider;

abstract class MysqlTenancyTestCase extends MysqlTestCase
{
    protected function getPackageProviders($app): array
    {
        require_once __DIR__.'/../Fixtures/Tenant.php';
        require_once __DIR__.'/../Fixtures/User.php';
        require_once __DIR__.'/../Fixtures/UserFactory.php';

        return [TenancyServiceProvider::class, BlueprintServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('blueprint.profile', 'tenant-saas');
        $app['config']->set('queue.connections.database.connection', null);
        $app['config']->set('cache.stores.database.connection', null);
        $app['config']->set('cache.stores.database.lock_connection', null);
        $app['config']->set('tenancy.tenant_model', Tenant::class);
        $app['config']->set('tenancy.central_domains', ['localhost']);
        $app['config']->set('tenancy.database.central_connection', 'testing');
        $app['config']->set('tenancy.database.prefix', '');
        $app['config']->set('tenancy.bootstrappers', [
            DatabaseTenancyBootstrapper::class,
            QueueTenancyBootstrapper::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Event::listen(TenancyInitialized::class, BootstrapTenancy::class);
        Event::listen(TenancyEnded::class, RevertToCentralContext::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../vendor/stancl/tenancy/assets/migrations');
    }
}
