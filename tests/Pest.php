<?php

use Best\Blueprint\Tests\TenancyTestCase;
use Best\Blueprint\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');
pest()->extend(TenancyTestCase::class)->in('Tenancy');

/** Feature/setting tables from the package's own migration stubs, on the current default connection. */
function createFlagTables(): void
{
    (require __DIR__.'/../stubs/features-migration.stub')->up();
    (require __DIR__.'/../stubs/settings-migration.stub')->up();
}

function useTestFlags(): void
{
    require_once __DIR__.'/Fixtures/TestFeatures.php';
    require_once __DIR__.'/Fixtures/TestSettings.php';
    config()->set('blueprint.features', Best\Blueprint\Tests\Fixtures\TestFeatures::class);
    config()->set('blueprint.settings', Best\Blueprint\Tests\Fixtures\TestSettings::class);
}
