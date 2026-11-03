<?php

use Best\Blueprint\Facades\Setting;
use Best\Blueprint\Flags\AdminOnly;
use Best\Blueprint\Settings\SettingAdmin;
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

it('keeps settings per tenant', function () {
    tenancy()->find('a')->run(fn () => app(SettingAdmin::class)->set('orders.limit', 99));

    expect(tenancy()->find('a')->run(fn () => Setting::get('orders.limit')))->toBe(99)
        ->and(tenancy()->find('b')->run(fn () => Setting::get('orders.limit')))->toBe(10);
});

it('falls back to defaults without a tenant and refuses writes', function () {
    expect(Setting::get('orders.limit'))->toBe(10)
        ->and(fn () => app(SettingAdmin::class)->set('orders.limit', 5))->toThrow(AdminOnly::class);
});
