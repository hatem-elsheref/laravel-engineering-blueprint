<?php

namespace Best\Blueprint\Tests;

use Best\Blueprint\BlueprintServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BlueprintServiceProvider::class];
    }
}
