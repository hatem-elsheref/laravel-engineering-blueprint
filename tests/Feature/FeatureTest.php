<?php

use Best\Blueprint\Facades\Feature;
use Best\Blueprint\Features\FeatureAdmin;
use Best\Blueprint\Features\UnknownFeature;
use Best\Blueprint\Flags\AdminGuard;
use Best\Blueprint\Flags\AdminOnly;
use Best\Blueprint\Flags\FlagContext;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('blueprint.profile', 'saas');
    useTestFlags();
    createFlagTables();
});

it('reads feature states', function () {
    DB::table('features')->insert([['key' => 'orders', 'enabled' => true], ['key' => 'reports', 'enabled' => false]]);

    expect(Feature::enabled('orders'))->toBeTrue()
        ->and(Feature::enabled('reports'))->toBeFalse()
        ->and(Feature::disabled('reports'))->toBeTrue()
        ->and(Feature::all())->toBe(['orders' => true, 'reports' => false]);
});

it('treats a missing row as disabled', function () {
    expect(Feature::enabled('orders'))->toBeFalse();
});

it('rejects unknown keys', function () {
    expect(fn () => Feature::enabled('nope'))->toThrow(UnknownFeature::class, 'Unknown feature "nope".');
});

it('sees admin changes immediately', function () {
    expect(Feature::enabled('reports'))->toBeFalse();

    app(FeatureAdmin::class)->enable('reports');

    expect(Feature::enabled('reports'))->toBeTrue();

    app(FeatureAdmin::class)->disable('reports');

    expect(Feature::enabled('reports'))->toBeFalse();
});

it('syncs only missing features', function () {
    DB::table('features')->insert(['key' => 'orders', 'enabled' => false]);

    expect(app(FeatureAdmin::class)->sync())->toBe(1)
        ->and(DB::table('features')->pluck('enabled', 'key')->map(fn ($v) => (bool) $v)->all())->toBe(['orders' => false, 'reports' => false]);
});

it('refuses admin writes during http requests', function () {
    app()->instance(AdminGuard::class, new class(app(FlagContext::class)) extends AdminGuard
    {
        protected function inHttpRequest(): bool
        {
            return true;
        }
    });

    expect(fn () => app(FeatureAdmin::class)->enable('reports'))
        ->toThrow(AdminOnly::class, 'Feature and setting changes are for platform admins');
});

it('forgets state between jobs', function () {
    DB::table('features')->insert(['key' => 'reports', 'enabled' => false]);
    expect(Feature::enabled('reports'))->toBeFalse();

    DB::table('features')->where('key', 'reports')->update(['enabled' => true]); // another process changed it
    app()->forgetScopedInstances(); // what the queue worker and Octane do between jobs/requests
    Feature::clearResolvedInstances();

    expect(Feature::enabled('reports'))->toBeTrue();
});

it('needs a profile with features', function () {
    config()->set('blueprint.profile', 'modular');

    expect(fn () => Feature::enabled('orders'))->toThrow(RuntimeException::class, 'Features need the saas or tenant-saas profile.');
});
