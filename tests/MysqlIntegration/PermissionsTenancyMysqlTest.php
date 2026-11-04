<?php

use Best\Blueprint\Testing\InteractsWithTenancy;
use Best\Blueprint\Tests\Fixtures\TestPermissions;
use Best\Blueprint\Tests\MysqlIntegration\MysqlEnvironment;
use Best\Blueprint\Tests\MysqlIntegration\MysqlSpatieTenancyTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(MysqlSpatieTenancyTestCase::class, InteractsWithTenancy::class);

const PTM_USERS_MIGRATION = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }
};
PHP;

final class PtmCentralPermissions
{
    /** @return array<string, list<string>> */
    public static function groups(): array
    {
        return ['tenants' => ['manage'], 'billing' => ['view']];
    }

    /** @return array<string, list<string>> */
    public static function roles(): array
    {
        return ['operator' => ['tenants.*']];
    }
}

beforeEach(function () {
    MysqlEnvironment::freshCentralTenancySchema($this);

    require_once __DIR__.'/../Fixtures/TestPermissions.php';
    config()->set('blueprint.permissions', TestPermissions::class);
    config()->set('blueprint.central_permissions', PtmCentralPermissions::class);

    $this->tenantMigrationsDir = database_path('migrations/tenant');

    File::ensureDirectoryExists($this->tenantMigrationsDir);
    File::put($this->tenantMigrationsDir.'/2026_01_01_000000_create_users_table.php', PTM_USERS_MIGRATION);
    File::copy(
        __DIR__.'/../../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub',
        $this->tenantMigrationsDir.'/2026_01_01_000300_create_permission_tables.php',
    );

    (require __DIR__.'/../../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();
});

afterEach(function () {
    $dir = $this->tenantMigrationsDir ?? null;

    if (is_string($dir) && is_dir($dir)) {
        File::deleteDirectory($dir);
    }
});

it('syncs tenant and central permission tables on mysql', function (): void {
    $tenant = $this->createTestTenant('sync');

    $this->artisan('tenants:migrate', ['--tenants' => [$tenant->id], '--force' => true])->assertExitCode(0);

    $this->artisan('permissions:sync')
        ->expectsOutputToContain('added 3 permission(s)')
        ->assertExitCode(0);

    $tenantNames = $tenant->run(
        fn () => DB::table('permissions')->pluck('name')->sort()->values()->all(),
    );

    expect($tenantNames)->toBe(['orders.view', 'products.update', 'products.view']);

    tenancy()->end();

    $this->artisan('permissions:sync', ['--central' => true])
        ->expectsOutputToContain('central: added 2 permission(s)')
        ->assertExitCode(0);

    expect(DB::connection('testing')->table('permissions')->pluck('name')->sort()->values()->all())
        ->toBe(['billing.view', 'tenants.manage']);
});
