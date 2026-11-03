<?php

declare(strict_types=1);

namespace Best\Blueprint\Tests\MysqlIntegration;

use Spatie\Permission\PermissionServiceProvider;

/** tenant-saas on MySQL with spatie/laravel-permission available. */
abstract class MysqlSpatieTenancyTestCase extends MysqlTenancyTestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), PermissionServiceProvider::class];
    }
}
