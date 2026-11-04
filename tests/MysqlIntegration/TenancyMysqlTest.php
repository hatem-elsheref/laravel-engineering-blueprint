<?php

use Best\Blueprint\Testing\InteractsWithTenancy;
use Best\Blueprint\Tests\MysqlIntegration\MysqlEnvironment;
use Best\Blueprint\Tests\MysqlIntegration\MysqlTenancyTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(MysqlTenancyTestCase::class, InteractsWithTenancy::class);

beforeEach(function (): void {
    MysqlEnvironment::freshCentralTenancySchema($this);
});

it('keeps tenant tables off the central mysql database', function (): void {
    $tenant = $this->createTestTenant('shop');

    expect(tenancy()->initialized)->toBeTrue()
        ->and(DB::getDefaultConnection())->toBe('tenant');

    Schema::create('tenant_probe', function ($table) {
        $table->integer('id')->primary();
    });

    tenancy()->end();

    expect(Schema::connection('testing')->hasTable('tenant_probe'))->toBeFalse()
        ->and($tenant->run(fn () => Schema::hasTable('tenant_probe')))->toBeTrue();

    $tenant->run(fn () => Schema::dropIfExists('tenant_probe'));
});
