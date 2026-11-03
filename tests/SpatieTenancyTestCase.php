<?php

namespace Best\Blueprint\Tests;

use Spatie\Permission\PermissionServiceProvider;

/** The tenancy test case with spatie/laravel-permission loaded too. */
abstract class SpatieTenancyTestCase extends TenancyTestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), PermissionServiceProvider::class];
    }
}
