<?php

use Best\Blueprint\Testing\InteractsWithTenancy;
use Illuminate\Support\Facades\DB;

uses(InteractsWithTenancy::class);

it('creates and starts a test tenant', function () {
    $tenant = $this->createTestTenant();

    expect(tenancy()->initialized)->toBeTrue()
        ->and(tenant('id'))->toBe('test')
        ->and($tenant->domains()->pluck('domain')->all())->toBe(['test.localhost'])
        ->and(DB::getDefaultConnection())->toBe('tenant')
        ->and(file_exists(database_path('blueprint_test_test')))->toBeTrue();
});

it('builds tenant urls', function () {
    $this->createTestTenant('shop');

    expect($this->tenantUrl('/api/x'))->toBe('http://shop.localhost/api/x');
});

it('cleans up its tenants', function () {
    $this->createTestTenant();

    $this->cleanUpTestTenants();

    expect(tenancy()->initialized)->toBeFalse()
        ->and(file_exists(database_path('blueprint_test_test')))->toBeFalse()
        ->and(App\Models\Tenant::find('test'))->toBeNull()
        ->and(DB::table('domains')->count())->toBe(0);
});

it('gives parallel workers their own tenant', function () {
    Illuminate\Support\Facades\ParallelTesting::resolveTokenUsing(fn () => '3');

    $this->createTestTenant();

    expect(tenant('id'))->toBe('test-3')
        ->and(file_exists(database_path('blueprint_test_test_3')))->toBeTrue();

    Illuminate\Support\Facades\ParallelTesting::resolveTokenUsing(fn () => false);
});

it('refuses when tenancy is not bootstrapped', function () {
    Illuminate\Support\Facades\Event::forget(Stancl\Tenancy\Events\TenancyInitialized::class);

    expect(fn () => $this->createTestTenant())->toThrow(RuntimeException::class, 'Tenancy is not bootstrapped');
});

it('runs its cleanup before other teardown callbacks', function () {
    // Like DatabaseMigrations' rollback, registered before the tenant exists.
    $this->beforeApplicationDestroyed(fn () => null);

    $this->createTestTenant();

    $first = (fn () => $this->beforeApplicationDestroyedCallbacks[0])->call($this);

    expect((new ReflectionFunction($first))->getName())->toBe('cleanUpTestTenants');
});
