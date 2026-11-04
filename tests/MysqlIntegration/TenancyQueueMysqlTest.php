<?php

use Best\Blueprint\Testing\InteractsWithTenancy;
use Best\Blueprint\Tests\Fixtures\RecordTenantJob;
use Best\Blueprint\Tests\MysqlIntegration\MysqlEnvironment;
use Best\Blueprint\Tests\MysqlIntegration\MysqlTenancyTestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(MysqlTenancyTestCase::class, InteractsWithTenancy::class);

beforeEach(function (): void {
    MysqlEnvironment::freshCentralTenancySchema($this);
    $this->tenantMigrationsDir = database_path('migrations/tenant');
    File::copyDirectory(__DIR__.'/../Fixtures/tenant-migrations', $this->tenantMigrationsDir);
});

afterEach(function (): void {
    $dir = $this->tenantMigrationsDir ?? null;

    if (is_string($dir) && is_dir($dir)) {
        File::deleteDirectory($dir);
    }
});

function mysqlRecordsOf(string $id): array
{
    return tenancy()->find($id)->run(fn () => DB::table('records')->pluck('tenant')->all());
}

it('runs queued jobs in the correct tenant database on mysql', function (): void {
    require_once __DIR__.'/../Fixtures/RecordTenantJob.php';

    $tenantA = $this->createTestTenant('qa');
    $tenantB = $this->createTestTenant('qb');

    tenancy()->end();

    Schema::connection('testing')->create('jobs', function (Blueprint $table) {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });

    config()->set('queue.default', 'database');

    tenancy()->initialize($tenantA);
    RecordTenantJob::dispatch();
    tenancy()->end();

    tenancy()->initialize($tenantB);
    RecordTenantJob::dispatch();
    tenancy()->end();

    expect(DB::connection('testing')->table('jobs')->count())->toBe(2);

    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertExitCode(0);
    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertExitCode(0);

    expect(mysqlRecordsOf($tenantA->id))->toBe([$tenantA->id])
        ->and(mysqlRecordsOf($tenantB->id))->toBe([$tenantB->id]);
});
