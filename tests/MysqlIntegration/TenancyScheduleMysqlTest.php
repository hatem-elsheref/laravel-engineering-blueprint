<?php

use Best\Blueprint\Testing\InteractsWithTenancy;
use Best\Blueprint\Tests\MysqlIntegration\MysqlTenancyTestCase;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(MysqlTenancyTestCase::class, InteractsWithTenancy::class);

beforeEach(function (): void {
    $this->tenantMigrationsDir = database_path('migrations/tenant');
    File::copyDirectory(__DIR__.'/../Fixtures/tenant-migrations', $this->tenantMigrationsDir);
});

afterEach(function (): void {
    $dir = $this->tenantMigrationsDir ?? null;

    if (is_string($dir) && is_dir($dir)) {
        File::deleteDirectory($dir);
    }
});

function mysqlScheduleRecordsOf(object $tenant): array
{
    return $tenant->run(fn () => DB::table('records')->pluck('tenant')->all());
}

it('runs tenants:run against each tenant database on mysql', function (): void {
    $tenantA = $this->createTestTenant('sched-a');
    $tenantB = $this->createTestTenant('sched-b');

    tenancy()->end();

    app(Kernel::class)->registerCommand(
        new ClosureCommand('test:record-tenant', fn () => DB::table('records')->insert(['tenant' => tenant('id')])),
    );

    $this->artisan('tenants:run', ['commandname' => 'test:record-tenant'])->assertExitCode(0);

    expect(mysqlScheduleRecordsOf($tenantA))->toBe([$tenantA->id])
        ->and(mysqlScheduleRecordsOf($tenantB))->toBe([$tenantB->id]);
});
