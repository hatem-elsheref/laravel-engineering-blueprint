<?php

use App\Models\User;
use App\Modules\Kiosk\Models\Badge;
use App\Modules\Kiosk\Models\Coupon;
use Best\Blueprint\Testing\InteractsWithPermissions;
use Best\Blueprint\Testing\InteractsWithTenancy;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

uses(InteractsWithTenancy::class, InteractsWithPermissions::class);

const BADGE_ENTRY = "'badges' => ['view', 'create', 'update', 'delete'],";
const REGION_ENTRY = "'regions' => ['view', 'create', 'update', 'delete'],";

// Module/entity names here must not be used by other test files: generated classes stay loaded for the run.
beforeEach(function () {
    config()->set('blueprint.module_path', 'app/Modules');
    config()->set('auth.providers.users.model', User::class); // as in a real app: spatie finds the User's guard from it
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    makePermissionDefinitions('Permissions', 'CentralPermissions');

    spl_autoload_register(function (string $class): void {
        $roots = ['App\\Modules\\' => app_path('Modules/'), 'Database\\Factories\\Kiosk\\' => database_path('factories/Kiosk/')];

        foreach ($roots as $prefix => $dir) {
            if (str_starts_with($class, $prefix) && is_file($file = $dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php')) {
                require_once $file;
            }
        }
    });

    File::ensureDirectoryExists(database_path('migrations/tenant'));
    File::copy(
        __DIR__.'/../../vendor/orchestra/testbench-core/laravel/migrations/0001_01_01_000000_testbench_create_users_table.php',
        database_path('migrations/tenant/0001_01_01_000000_create_users_table.php'),
    );
    File::copy(
        __DIR__.'/../../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub',
        database_path('migrations/tenant/2026_01_01_000300_create_permission_tables.php'),
    );

    $this->artisan('make:module', ['name' => 'Kiosk', '--api' => true, '--web' => true])->assertExitCode(0);
    $this->artisan('make:module', ['name' => 'Hq', '--api' => true, '--central' => true])->assertExitCode(0);
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::deleteDirectory(app_path('Shared'));
    File::deleteDirectory(database_path('migrations/tenant'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Kiosk'));
    File::deleteDirectory(database_path('factories/Hq'));
    File::deleteDirectory(resource_path('views/kiosk'));
    File::deleteDirectory(resource_path('views/layouts'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    file_put_contents($this->providersPath, $this->providers);
});

it('adds tenant groups to Permissions and central groups to CentralPermissions', function () {
    $this->artisan('make:crud', ['module' => 'Kiosk', 'entity' => 'Badge', '--permissions' => true])
        ->expectsOutputToContain("added 'badges' to app/Shared/Permissions/Permissions.php")
        ->expectsOutputToContain("Next: add 'badges.*' (or single permissions) to a role in app/Shared/Permissions/Permissions.php, then php artisan permissions:sync")
        ->assertExitCode(0);
    $this->artisan('make:crud', ['module' => 'Hq', 'entity' => 'Region', '--permissions' => true])
        ->expectsOutputToContain("added 'regions' to app/Shared/Permissions/CentralPermissions.php")
        ->expectsOutputToContain("Next: add 'regions.*' (or single permissions) to a role in app/Shared/Permissions/CentralPermissions.php, then php artisan permissions:sync --central")
        ->assertExitCode(0);

    $tenant = file_get_contents(app_path('Shared/Permissions/Permissions.php'));
    $central = file_get_contents(app_path('Shared/Permissions/CentralPermissions.php'));

    expect($tenant)->toContain(BADGE_ENTRY)->not->toContain(REGION_ENTRY)
        ->and($central)->toContain(REGION_ENTRY)->not->toContain(BADGE_ENTRY)
        ->and(file_get_contents(app_path('Modules/Hq/Policies/RegionPolicy.php')))->toContain("private const GROUP = 'regions';");
});

it('writes tenant tests that set up permissions inside the tenant', function () {
    $this->artisan('make:crud', ['module' => 'Kiosk', 'entity' => 'Badge', '--api' => true, '--web' => true, '--permissions' => true])->assertExitCode(0);

    foreach (['BadgeApiTest', 'BadgeWebTest'] as $name) {
        $path = base_path("tests/Feature/Modules/Kiosk/{$name}.php");
        $test = file_get_contents($path);

        expect($test)->toContain('uses(InteractsWithTenancy::class, DatabaseMigrations::class, InteractsWithPermissions::class);')
            ->toContain("    \$this->tenant = \$this->createTestTenant();\n    \$this->user = User::factory()->create();\n    \$this->grantPermissions(\$this->user, 'badges.view', 'badges.create', 'badges.update', 'badges.delete');\n})")
            ->toContain("it('denies users without the permission'")
            ->toContain("it('lets a super-admin in'")
            ->toContain('$this->tenantUrl(');

        $lint = new Process([PHP_BINARY, '-l', $path]);
        $lint->run();
        expect($lint->getExitCode())->toBe(0, $lint->getOutput());
    }
});

it('serves the generated tenant crud by permission, like the generated test does', function () {
    $this->artisan('make:crud', ['module' => 'Kiosk', 'entity' => 'Badge', '--api' => true, '--web' => true, '--permissions' => true, '--fields' => 'name:string'])->assertExitCode(0);
    app()->register('App\Modules\Kiosk\Provider');

    $this->createTestTenant();
    $user = User::factory()->create();
    $this->grantPermissions($user, 'badges.view', 'badges.create', 'badges.update', 'badges.delete');

    $id = $this->actingAs($user)->postJson($this->tenantUrl('/api/badges'), ['name' => 'Gold'])->assertCreated()->json('data.id');
    $this->actingAs($user)->getJson($this->tenantUrl('/api/badges'))->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($user)->putJson($this->tenantUrl("/api/badges/{$id}"), ['name' => 'Silver'])->assertOk();
    $this->actingAs($user)->get($this->tenantUrl('/badges'))->assertOk();

    $other = User::factory()->create();
    $this->actingAs($other)->getJson($this->tenantUrl('/api/badges'))->assertForbidden();
    $this->actingAs($other)->deleteJson($this->tenantUrl("/api/badges/{$id}"))->assertForbidden();
    $this->actingAs($other)->get($this->tenantUrl('/badges'))->assertForbidden();

    $admin = User::factory()->create();
    $this->makeSuperAdmin($admin);
    $this->actingAs($admin)->getJson($this->tenantUrl('/api/badges'))->assertOk();

    $this->actingAs($user)->deleteJson($this->tenantUrl("/api/badges/{$id}"))->assertNoContent();
    expect(Badge::count())->toBe(0);
});

it('denies instead of erroring when the tenant never synced permissions', function () {
    $this->artisan('make:crud', ['module' => 'Kiosk', 'entity' => 'Badge', '--permissions' => true, '--fields' => 'name:string'])->assertExitCode(0);
    app()->register('App\Modules\Kiosk\Provider');

    $this->createTestTenant();
    $user = User::factory()->create();

    $this->actingAs($user)->getJson($this->tenantUrl('/api/badges'))->assertForbidden();
    $this->actingAs($user)->postJson($this->tenantUrl('/api/badges'), ['name' => 'Gold'])->assertForbidden();
});

it('serves an owned tenant crud by permission and owner', function () {
    $this->artisan('make:crud', ['module' => 'Kiosk', 'entity' => 'Coupon', '--api' => true, '--web' => true, '--permissions' => true, '--owned' => true, '--fields' => 'name:string'])->assertExitCode(0);
    app()->register('App\Modules\Kiosk\Provider');

    expect(file_get_contents(app_path('Shared/Permissions/Permissions.php')))->toContain("'coupons' => ['view', 'create', 'update', 'delete', 'any'],")
        ->and(file_get_contents(glob(database_path('migrations/tenant/*_create_coupons_table.php'))[0]))->toContain("\$table->foreignId('user_id')->constrained()->cascadeOnDelete();");

    $this->createTestTenant();
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $this->grantPermissions($owner, 'coupons.view', 'coupons.create', 'coupons.update', 'coupons.delete');
    $this->grantPermissions($other, 'coupons.view', 'coupons.create', 'coupons.update', 'coupons.delete');

    $id = $this->actingAs($owner)->postJson($this->tenantUrl('/api/coupons'), ['name' => 'Gold'])->assertCreated()->json('data.id');
    expect(Coupon::find($id)->user_id)->toBe($owner->id);
    $this->actingAs($owner)->getJson($this->tenantUrl("/api/coupons/{$id}"))->assertOk();

    $this->actingAs($other)->getJson($this->tenantUrl("/api/coupons/{$id}"))->assertNotFound();
    $this->actingAs($other)->putJson($this->tenantUrl("/api/coupons/{$id}"), ['name' => 'Silver'])->assertNotFound();
    $this->actingAs($other)->deleteJson($this->tenantUrl("/api/coupons/{$id}"))->assertNotFound();
    $this->actingAs($other)->getJson($this->tenantUrl('/api/coupons'))->assertOk()->assertJsonCount(0, 'data');

    $this->grantPermissions($other, 'coupons.view', 'coupons.update', 'coupons.any');
    $this->actingAs($other)->getJson($this->tenantUrl('/api/coupons'))->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($other)->putJson($this->tenantUrl("/api/coupons/{$id}"), ['name' => 'Silver'])->assertOk();
    expect(Coupon::find($id)->user_id)->toBe($owner->id);

    $admin = User::factory()->create();
    $this->makeSuperAdmin($admin);
    $this->actingAs($admin)->getJson($this->tenantUrl('/api/coupons'))->assertOk()->assertJsonCount(1, 'data');
});

it('writes owned tests for tenant and central modules', function () {
    $this->artisan('make:crud', ['module' => 'Kiosk', 'entity' => 'Coupon', '--api' => true, '--web' => true, '--permissions' => true, '--owned' => true])->assertExitCode(0);
    $this->artisan('make:crud', ['module' => 'Hq', 'entity' => 'Office', '--permissions' => true, '--owned' => true])->assertExitCode(0);

    expect(file_get_contents(app_path('Shared/Permissions/CentralPermissions.php')))->toContain("'offices' => ['view', 'create', 'update', 'delete', 'any'],");

    foreach (['Kiosk/CouponApiTest', 'Kiosk/CouponWebTest', 'Hq/OfficeApiTest'] as $name) {
        $path = base_path("tests/Feature/Modules/{$name}.php");
        $test = file_get_contents($path);

        expect($test)->toContain('->for($this->user)->create();')
            ->toContain("it('lets an any-holder act on every")
            ->toContain("it('lets a super-admin see every");

        $lint = new Process([PHP_BINARY, '-l', $path]);
        $lint->run();
        expect($lint->getExitCode())->toBe(0, $lint->getOutput());
    }

    expect(file_get_contents(base_path('tests/Feature/Modules/Kiosk/CouponApiTest.php')))->toContain('$this->tenantUrl(');
});
