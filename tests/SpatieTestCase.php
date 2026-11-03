<?php

namespace Best\Blueprint\Tests;

use Spatie\Permission\PermissionServiceProvider;

/** The package test case with spatie/laravel-permission loaded and its tables created. */
abstract class SpatieTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), PermissionServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        (require __DIR__.'/../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();
    }
}
