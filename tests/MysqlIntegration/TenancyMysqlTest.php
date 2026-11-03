<?php

use Best\Blueprint\Testing\InteractsWithTenancy;
use Best\Blueprint\Tests\MysqlIntegration\MysqlTenancyTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(MysqlTenancyTestCase::class, InteractsWithTenancy::class, DatabaseMigrations::class);

it('keeps tenant tables off the central mysql database', function (): void {
    $tenant = $this->createTestTenant('shop');

    expect(tenancy()->initialized)->toBeTrue()
        ->and(DB::getDefaultConnection())->toBe('tenant');

    DB::statement('CREATE TABLE tenant_probe (id INT PRIMARY KEY)');

    tenancy()->end();

    expect(Schema::connection('testing')->hasTable('tenant_probe'))->toBeFalse();

    tenancy()->initialize($tenant);

    expect(DB::select('SELECT 1 AS ok FROM tenant_probe'))->not->toBeEmpty();

    DB::statement('DROP TABLE tenant_probe');
});
