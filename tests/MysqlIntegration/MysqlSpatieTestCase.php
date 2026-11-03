<?php

declare(strict_types=1);

namespace Best\Blueprint\Tests\MysqlIntegration;

use Spatie\Permission\PermissionServiceProvider;

/** MySQL integration with spatie/laravel-permission tables on the central connection. */
abstract class MysqlSpatieTestCase extends MysqlTestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), PermissionServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        (require __DIR__.'/../../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();
    }
}
