<?php

namespace Best\Blueprint\Tests;

use Best\Blueprint\BlueprintServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getEnvironmentSetUp($app): void
    {
        // Real apps always have one; web routes (sessions, cookies) need it.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    protected function getPackageProviders($app): array
    {
        return [BlueprintServiceProvider::class];
    }
}
