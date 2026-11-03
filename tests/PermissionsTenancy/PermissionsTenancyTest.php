<?php

use Best\Blueprint\Tenancy\TenantSetup;
use Best\Blueprint\Tests\Fixtures\RoleUser;
use Best\Blueprint\Tests\Fixtures\TestPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

const PT_USERS_MIGRATION = <<<'PHP'
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

final class PtCentralPermissions
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

/** Names in the central permissions/roles tables (spatie's tables on the central connection). */
function centralPermissionNames(): array
{
    return DB::connection('testing')->table('permissions')->pluck('name')->sort()->values()->all();
}

function tenantPermissionNames(string $id): array
{
    return tenancy()->find($id)->run(fn () => DB::table('permissions')->pluck('name')->sort()->values()->all());
}

beforeEach(function () {
    require_once __DIR__.'/../Fixtures/TestPermissions.php';
    require_once __DIR__.'/../Fixtures/RoleUser.php';
    config()->set('blueprint.permissions', TestPermissions::class);
    config()->set('blueprint.central_permissions', PtCentralPermissions::class);

    File::ensureDirectoryExists(database_path('migrations/tenant'));
    File::put(database_path('migrations/tenant/2026_01_01_000000_create_users_table.php'), PT_USERS_MIGRATION);
    File::copy(
        __DIR__.'/../../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub',
        database_path('migrations/tenant/2026_01_01_000300_create_permission_tables.php'),
    );

    // The central database gets spatie's tables too (the central admin side).
    (require __DIR__.'/../../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();

    foreach (['a', 'b'] as $id) {
        app(TenantSetup::class)->run($id, "{$id}.localhost", "tenant_{$id}");
    }
});

afterEach(function () {
    File::deleteDirectory(database_path('migrations/tenant'));
});

it('syncs tenant definitions into every tenant and never the central database', function () {
    tenancy()->find('a')->run(fn () => DB::table('permissions')->delete());

    $this->artisan('permissions:sync')
        ->expectsOutputToContain('tenant "a": added 3 permission(s)')
        ->expectsOutputToContain('tenant "b": added 0 permission(s)')
        ->assertExitCode(0);

    expect(tenantPermissionNames('a'))->toBe(['orders.view', 'products.update', 'products.view'])
        ->and(tenantPermissionNames('b'))->toBe(['orders.view', 'products.update', 'products.view'])
        ->and(centralPermissionNames())->toBe([]);
});

it('syncs only the requested tenants', function () {
    tenancy()->find('a')->run(fn () => DB::table('permissions')->delete());
    tenancy()->find('b')->run(fn () => DB::table('permissions')->delete());

    $this->artisan('permissions:sync', ['--tenant' => ['b']])->assertExitCode(0);

    expect(tenantPermissionNames('a'))->toBe([])
        ->and(tenantPermissionNames('b'))->toHaveCount(3);
});

it('syncs central definitions only into the central database', function () {
    $this->artisan('permissions:sync', ['--central' => true])
        ->expectsOutputToContain('central: added 2 permission(s) and 2 role(s)')
        ->assertExitCode(0);

    expect(centralPermissionNames())->toBe(['billing.view', 'tenants.manage'])
        ->and(DB::connection('testing')->table('roles')->pluck('name')->sort()->values()->all())->toBe(['operator', 'super-admin'])
        ->and(tenantPermissionNames('a'))->not->toContain('tenants.manage')->not->toContain('billing.view')
        ->and(tenantPermissionNames('b'))->not->toContain('tenants.manage');
});

it('refuses --central with --tenant', function () {
    $this->artisan('permissions:sync', ['--central' => true, '--tenant' => ['a']])
        ->expectsOutputToContain('--central and --tenant cannot be combined')
        ->assertExitCode(1);

    expect(centralPermissionNames())->toBe([]);
});

it('refuses an unknown tenant before syncing any', function () {
    tenancy()->find('a')->run(fn () => DB::table('permissions')->delete());

    $this->artisan('permissions:sync', ['--tenant' => ['a', 'nope']])
        ->expectsOutputToContain('Tenant "nope" not found.')
        ->assertExitCode(1);

    expect(tenantPermissionNames('a'))->toBe([]);
});

it('reports a failing tenant, exits 1 and still syncs the others', function () {
    tenancy()->find('a')->run(fn () => Schema::drop('role_has_permissions'));
    tenancy()->find('b')->run(fn () => DB::table('permissions')->delete());

    $this->artisan('permissions:sync')
        ->expectsOutputToContain('Tenant "a":')
        ->assertExitCode(1);

    expect(tenantPermissionNames('b'))->toHaveCount(3);
});

it('refuses central definition errors before touching the central database', function () {
    config()->set('blueprint.central_permissions', 'App\\Missing\\CentralPermissions');

    $this->artisan('permissions:sync', ['--central' => true])
        ->expectsOutputToContain('App\\Missing\\CentralPermissions')
        ->assertExitCode(1);
});

it('tenant:create syncs permissions', function () {
    expect(tenantPermissionNames('a'))->toBe(['orders.view', 'products.update', 'products.view'])
        ->and(tenancy()->find('a')->run(fn () => DB::table('roles')->pluck('name')->sort()->values()->all()))
        ->toBe(['editor', 'member', 'super-admin'])
        ->and(centralPermissionNames())->toBe([]);

    $this->artisan('tenant:create', ['name' => 'a'])
        ->expectsOutputToContain('– Sync permissions (already done)')
        ->assertExitCode(0);

    $this->artisan('tenant:create', ['name' => 'c'])
        ->expectsOutputToContain('✓ Sync permissions')
        ->assertExitCode(0);
});

it('tenant:create skips permissions when they are not set up', function () {
    config()->set('blueprint.permissions', 'App\\Missing\\Permissions');

    $this->artisan('tenant:create', ['name' => 'c'])
        ->expectsOutputToContain('– Sync permissions (skipped: run engineering:install --permissions to set up permissions)')
        ->assertExitCode(0);
});

it('tenant:create skips permissions when the tenant database has no permission tables', function () {
    File::delete(database_path('migrations/tenant/2026_01_01_000300_create_permission_tables.php'));

    $this->artisan('tenant:create', ['name' => 'c'])
        ->expectsOutputToContain('– Sync permissions (skipped: no permissions table in the tenant database)')
        ->assertExitCode(0);
});

it('does not reuse one tenant\'s permissions in another', function () {
    foreach (['a', 'b'] as $id) {
        tenancy()->find($id)->run(fn () => RoleUser::create(['name' => 'same id'])->assignRole('editor'));
    }

    // Same role and permission ids in both databases, but tenant B's editor can't view products.
    tenancy()->find('b')->run(fn () => DB::table('role_has_permissions')->delete());

    $check = fn (string $id) => tenancy()->find($id)->run(fn () => RoleUser::findOrFail(1)->checkPermissionTo('products.view'));

    expect($check('a'))->toBeTrue()
        ->and($check('b'))->toBeFalse()
        ->and($check('a'))->toBeTrue();

    tenancy()->initialize(tenancy()->find('b'));
    $key = app(PermissionRegistrar::class)->cacheKey;
    tenancy()->end();

    expect($key)->toBe('spatie.permission.cache.tenant.b')
        ->and(app(PermissionRegistrar::class)->cacheKey)->toBe('spatie.permission.cache');
});

it('hints at pruning the central database after a central sync', function () {
    DB::connection('testing')->table('permissions')->insert(['name' => 'legacy.view', 'guard_name' => 'web']);

    $this->artisan('permissions:sync', ['--central' => true])
        ->expectsOutputToContain('central: stale: legacy.view')
        ->expectsOutputToContain('Run permissions:sync --central --prune to delete them.')
        ->assertExitCode(0);
});

it('hints at pruning only the tenant that has stale entries', function () {
    tenancy()->find('b')->run(fn () => DB::table('permissions')->insert(['name' => 'legacy.view', 'guard_name' => 'web']));

    $this->artisan('permissions:sync')
        ->expectsOutputToContain('tenant "b": stale: legacy.view')
        ->expectsOutputToContain('Run permissions:sync --tenant=b --prune to delete them.')
        ->doesntExpectOutputToContain('Run permissions:sync --prune to delete them.')
        ->assertExitCode(0);
});

/** spatie <8: initializeCache() re-reads the cache config but keeps the loaded permissions in memory. */
final class PtKeepsPermissionsRegistrar extends PermissionRegistrar
{
    public function initializeCache(): void
    {
        $this->cacheExpirationTime = config('permission.cache.expiration_time') ?: DateInterval::createFromDateString('24 hours');
        $this->teams = (bool) config('permission.teams', false);
        $this->teamsKey = config('permission.column_names.team_foreign_key', 'team_id');
        $this->cacheKey = config('permission.cache.key');
        $this->pivotRole = config('permission.column_names.role_pivot_key') ?: 'role_id';
        $this->pivotPermission = config('permission.column_names.permission_pivot_key') ?: 'permission_id';
        $this->cacheManager = app('cache');
        $this->cache = $this->getCacheStoreFromConfig();
    }
}

it('rebuilds the loaded permissions on every tenant switch, also when spatie keeps them in initializeCache()', function () {
    app()->instance(PermissionRegistrar::class, new PtKeepsPermissionsRegistrar(app('cache')));

    tenancy()->find('b')->run(fn () => DB::table('permissions')->insert(['name' => 'b.only', 'guard_name' => 'web']));
    DB::connection('testing')->table('permissions')->insert(['name' => 'central.only', 'guard_name' => 'web']);

    $loaded = fn () => app(PermissionRegistrar::class)->getPermissions()->pluck('name')->sort()->values()->all();

    expect(tenancy()->find('a')->run($loaded))->toBe(['orders.view', 'products.update', 'products.view'])
        ->and(tenancy()->find('b')->run($loaded))->toBe(['b.only', 'orders.view', 'products.update', 'products.view'])
        ->and($loaded())->toBe(['central.only'])
        ->and(tenancy()->find('a')->run($loaded))->toBe(['orders.view', 'products.update', 'products.view']);
});

it('tells the user to migrate a tenant without permission tables, and still syncs the others', function () {
    tenancy()->find('a')->run(function () {
        Schema::drop('role_has_permissions');
        Schema::drop('model_has_permissions');
        Schema::drop('permissions');
    });
    tenancy()->find('b')->run(fn () => DB::table('permissions')->delete());

    $this->artisan('permissions:sync')
        ->expectsOutputToContain('Tenant "a": no permission tables — run php artisan tenants:migrate')
        ->assertExitCode(1);

    expect(tenantPermissionNames('b'))->toHaveCount(3)
        ->and(tenancy()->initialized)->toBeFalse();
});

it('tells the user to migrate when the central database has no permission tables', function () {
    Schema::connection('testing')->drop('role_has_permissions');
    Schema::connection('testing')->drop('model_has_permissions');
    Schema::connection('testing')->drop('permissions');

    $this->artisan('permissions:sync', ['--central' => true])
        ->expectsOutputToContain('central: no permission tables — run php artisan migrate')
        ->assertExitCode(1);
});

it('tenant:create finds the permission tables under their configured names', function () {
    config()->set('permission.table_names.permissions', 'acl_permissions');

    $this->artisan('tenant:create', ['name' => 'c'])
        ->expectsOutputToContain('✓ Sync permissions')
        ->assertExitCode(0);

    expect(tenancy()->find('c')->run(fn () => DB::table('acl_permissions')->count()))->toBe(3);
});
