<?php

use Best\Blueprint\Facades\Feature;
use Best\Blueprint\Features\FeatureAdmin;
use Best\Blueprint\Flags\AdminOnly;
use Best\Blueprint\Tenancy\TenantSetup;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    useTestFlags();
    File::ensureDirectoryExists(database_path('migrations/tenant'));
    File::copy(__DIR__.'/../../stubs/features-migration.stub', database_path('migrations/tenant/2026_01_01_000100_create_features_table.php'));
    File::copy(__DIR__.'/../../stubs/settings-migration.stub', database_path('migrations/tenant/2026_01_01_000200_create_settings_table.php'));

    foreach (['a', 'b'] as $id) {
        app(TenantSetup::class)->run($id, "{$id}.localhost", "tenant_{$id}");
    }
});

afterEach(function () {
    File::deleteDirectory(database_path('migrations/tenant'));
});

it('isolates tenants', function () {
    tenancy()->find('a')->run(fn () => app(FeatureAdmin::class)->enable('reports'));

    tenancy()->initialize(tenancy()->find('a'));
    $inA = Feature::enabled('reports');
    tenancy()->initialize(tenancy()->find('b'));
    $inB = Feature::enabled('reports');
    tenancy()->end();

    expect($inA)->toBeTrue()->and($inB)->toBeFalse();
});

it('is disabled without a tenant', function () {
    expect(Feature::enabled('orders'))->toBeFalse()
        ->and(Illuminate\Support\Facades\Schema::hasTable('features'))->toBeFalse();
});

it('refuses admin writes without a tenant', function () {
    expect(fn () => app(FeatureAdmin::class)->enable('reports'))->toThrow(AdminOnly::class, 'No tenant is active');
});
