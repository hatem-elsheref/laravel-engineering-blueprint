<?php

use Best\Blueprint\Facades\Feature;
use Best\Blueprint\Facades\Setting;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('blueprint.profile', 'saas');
    useTestFlags();
    createFlagTables();
});

it('enables and disables features for the app', function () {
    $this->artisan('feature:enable', ['key' => 'reports'])
        ->expectsOutputToContain('Feature "reports" enabled for the app.')
        ->assertExitCode(0);
    expect(Feature::enabled('reports'))->toBeTrue();

    $this->artisan('feature:disable', ['key' => 'reports'])
        ->expectsOutputToContain('Feature "reports" disabled for the app.')
        ->assertExitCode(0);
    Feature::clearResolvedInstances();
    app()->forgetScopedInstances();
    expect(Feature::enabled('reports'))->toBeFalse();
});

it('refuses unknown features', function () {
    $this->artisan('feature:enable', ['key' => 'nope'])
        ->expectsOutputToContain('Unknown feature "nope".')
        ->assertExitCode(1);
});

it('refuses --tenant in saas', function () {
    $this->artisan('feature:enable', ['key' => 'reports', '--tenant' => 'a'])
        ->expectsOutputToContain('--tenant only applies to the tenant-saas profile.')
        ->assertExitCode(1);
});

it('needs a profile with features', function () {
    config()->set('blueprint.profile', 'modular');

    $this->artisan('feature:list')
        ->expectsOutputToContain('Features and settings need the saas or tenant-saas profile.')
        ->assertExitCode(1);
});

it('lists features', function () {
    DB::table('features')->insert(['key' => 'orders', 'enabled' => true]);

    $this->artisan('feature:list')
        ->expectsTable(['Feature', 'Enabled', 'Default'], [['orders', 'yes', 'yes'], ['reports', 'no', 'no']])
        ->assertExitCode(0);
});

it('syncs missing features', function () {
    DB::table('features')->insert(['key' => 'orders', 'enabled' => false]);

    $this->artisan('features:sync')
        ->expectsOutputToContain('added 1 feature(s) to the app')
        ->assertExitCode(0);

    expect(DB::table('features')->where('key', 'orders')->value('enabled'))->toBeFalsy();
});

it('sets settings with json values', function (string $key, string $value, mixed $expected) {
    $this->artisan('setting:set', ['key' => $key, 'value' => $value])
        ->expectsOutputToContain("Setting \"{$key}\" updated for the app.")
        ->assertExitCode(0);

    app()->forgetScopedInstances();
    Setting::clearResolvedInstances();

    expect(Setting::get($key))->toBe($expected);
})->with([
    ['orders.auto_confirm', 'true', true],
    ['orders.limit', '7', 7],
    ['orders.statuses', '["a","b"]', ['a', 'b']],
    ['app.name', 'Acme Store', 'Acme Store'],
]);

it('refuses invalid setting values', function () {
    $this->artisan('setting:set', ['key' => 'orders.limit', 'value' => 'x'])
        ->expectsOutputToContain('Setting "orders.limit" must be of type int.')
        ->assertExitCode(1);
});

it('lists settings', function () {
    $this->artisan('setting:list')
        ->expectsOutputToContain('orders.limit')
        ->assertExitCode(0);
});
