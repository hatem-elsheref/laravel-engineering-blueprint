<?php

use Best\Blueprint\Facades\Feature;
use Best\Blueprint\Facades\Setting;
use Best\Blueprint\Tenancy\TenantSetup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

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

it('changes one tenant only', function () {
    $this->artisan('feature:enable', ['key' => 'reports', '--tenant' => 'a'])
        ->expectsOutputToContain('Feature "reports" enabled for tenant "a".')
        ->assertExitCode(0);

    expect(tenancy()->find('a')->run(fn () => Feature::enabled('reports')))->toBeTrue()
        ->and(tenancy()->find('b')->run(fn () => Feature::enabled('reports')))->toBeFalse()
        ->and(tenancy()->initialized)->toBeFalse();
});

it('requires --tenant', function () {
    $this->artisan('feature:enable', ['key' => 'reports'])
        ->expectsOutputToContain('--tenant is required in the tenant-saas profile.')
        ->assertExitCode(1);
});

it('refuses unknown tenants', function () {
    $this->artisan('feature:enable', ['key' => 'reports', '--tenant' => 'zzz'])
        ->expectsOutputToContain('Tenant "zzz" not found.')
        ->assertExitCode(1);
});

it('syncs every tenant', function () {
    // Tenants get every default at creation; simulate a feature defined after tenant "a" was created.
    tenancy()->find('a')->run(fn () => DB::table('features')->where('key', 'reports')->delete());

    $this->artisan('features:sync')
        ->expectsOutputToContain('added 1 feature(s) to tenant "a"')
        ->expectsOutputToContain('added 0 feature(s) to tenant "b"')
        ->assertExitCode(0);
});

it('sets a tenant setting', function () {
    $this->artisan('setting:set', ['key' => 'orders.limit', 'value' => '3', '--tenant' => 'b'])
        ->expectsOutputToContain('Setting "orders.limit" updated for tenant "b".')
        ->assertExitCode(0);

    expect(tenancy()->find('b')->run(fn () => Setting::get('orders.limit')))->toBe(3);
});

it('validates tenants before syncing any', function () {
    $this->artisan('features:sync', ['--tenant' => ['a', 'zzz']])
        ->expectsOutputToContain('Tenant "zzz" not found.')
        ->doesntExpectOutputToContain('added')
        ->assertExitCode(1);
});

it('reports a tenant without a features table instead of crashing', function () {
    tenancy()->find('b')->run(fn () => Schema::drop('features'));

    $this->artisan('features:sync')
        ->expectsOutputToContain('added 0 feature(s) to tenant "a"')
        ->expectsOutputToContain('tenant "b":')
        ->assertExitCode(1);
});
