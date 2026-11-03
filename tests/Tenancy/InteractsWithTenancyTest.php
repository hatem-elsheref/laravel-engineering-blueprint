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
        ->and(file_exists(database_path('tenant_test_test')))->toBeTrue();
});

it('builds tenant urls', function () {
    $this->createTestTenant('shop');

    expect($this->tenantUrl('/api/x'))->toBe('http://shop.localhost/api/x');
});

it('cleans up its tenants', function () {
    $this->createTestTenant();

    $this->cleanUpTestTenants();

    expect(tenancy()->initialized)->toBeFalse()
        ->and(file_exists(database_path('tenant_test_test')))->toBeFalse()
        ->and(App\Models\Tenant::find('test'))->toBeNull()
        ->and(DB::table('domains')->count())->toBe(0);
});
