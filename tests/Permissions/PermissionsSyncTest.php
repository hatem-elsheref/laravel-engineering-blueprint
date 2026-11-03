<?php

use Best\Blueprint\Permissions\PermissionsDetector;
use Best\Blueprint\Tests\Fixtures\TestPermissions;
use Best\Blueprint\Tests\Fixtures\TypoPermissions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    require_once __DIR__.'/../Fixtures/TestPermissions.php';
    config()->set('blueprint.profile', 'saas');
    config()->set('blueprint.permissions', TestPermissions::class);
});

it('creates permissions, roles and the super-admin', function () {
    $this->artisan('permissions:sync')->assertExitCode(0);

    expect(Permission::pluck('name')->sort()->values()->all())->toBe(['orders.view', 'products.update', 'products.view'])
        ->and(Role::pluck('name')->sort()->values()->all())->toBe(['editor', 'member', 'super-admin'])
        ->and(Role::findByName('editor', 'web')->permissions->pluck('name')->sort()->values()->all())->toBe(['products.update', 'products.view'])
        ->and(Role::findByName('member', 'web')->permissions)->toHaveCount(0);
});

it('is idempotent', function () {
    $this->artisan('permissions:sync')->assertExitCode(0);
    $this->artisan('permissions:sync')->expectsOutputToContain('added 0 permission(s) and 0 role(s)')->assertExitCode(0);

    expect(Permission::count())->toBe(3)->and(Role::count())->toBe(3);
});

it('makes the file authoritative for named roles', function () {
    $this->artisan('permissions:sync');
    $extra = Permission::create(['name' => 'extra.thing', 'guard_name' => 'web']);
    Role::findByName('editor', 'web')->givePermissionTo($extra);

    $this->artisan('permissions:sync');

    expect(Role::findByName('editor', 'web')->permissions->pluck('name')->all())->not->toContain('extra.thing');
});

it('lists stale entries without deleting them', function () {
    Permission::create(['name' => 'legacy.view', 'guard_name' => 'web']);

    $this->artisan('permissions:sync')->expectsOutputToContain('stale: legacy.view')->assertExitCode(0);

    expect(Permission::where('name', 'legacy.view')->exists())->toBeTrue();
});

it('never lists the super-admin as stale', function () {
    $this->artisan('permissions:sync');
    $this->artisan('permissions:sync')->doesntExpectOutputToContain('stale: super-admin')->assertExitCode(0);
});

it('prunes with --prune', function () {
    Permission::create(['name' => 'legacy.view', 'guard_name' => 'web']);
    Role::create(['name' => 'old-role', 'guard_name' => 'web']);

    $this->artisan('permissions:sync --prune')->assertExitCode(0);

    expect(Permission::where('name', 'legacy.view')->exists())->toBeFalse()
        ->and(Role::where('name', 'old-role')->exists())->toBeFalse()
        ->and(Role::where('name', 'super-admin')->exists())->toBeTrue();
});

it('fails on a definition error and changes nothing', function () {
    require_once __DIR__.'/../Fixtures/TypoPermissions.php';
    config()->set('blueprint.permissions', TypoPermissions::class);

    $this->artisan('permissions:sync')
        ->expectsOutputToContain('Role [editor] grants [produtcs.*]')
        ->assertExitCode(1);

    expect(Permission::count())->toBe(0)->and(Role::count())->toBe(0);
});

it('refuses without spatie', function () {
    app()->bind(PermissionsDetector::class, fn () => new class extends PermissionsDetector
    {
        public function spatie(): bool
        {
            return false;
        }
    });

    $this->artisan('permissions:sync')->expectsOutputToContain('composer require spatie/laravel-permission')->assertExitCode(1);
});

it('refuses tenant options outside tenant-saas', function () {
    $this->artisan('permissions:sync --central')
        ->expectsOutputToContain('--tenant and --central only apply to the tenant-saas profile.')
        ->assertExitCode(1);
    $this->artisan('permissions:sync --tenant=1')
        ->expectsOutputToContain('--tenant and --central only apply to the tenant-saas profile.')
        ->assertExitCode(1);
});

it('rolls back everything when the sync fails midway', function () {
    Role::creating(function () {
        throw new RuntimeException('boom');
    });

    expect(fn () => $this->artisan('permissions:sync')->run())->toThrow(RuntimeException::class);
    expect(DB::table('permissions')->count())->toBe(0);
});

it('hints at a plain --prune outside tenant-saas', function () {
    Permission::create(['name' => 'legacy.view', 'guard_name' => 'web']);

    $this->artisan('permissions:sync')
        ->expectsOutputToContain('Run permissions:sync --prune to delete them.')
        ->assertExitCode(0);
});

it('refuses an unsupported spatie setup', function (Closure $setup, string $message) {
    $setup();

    $this->artisan('permissions:sync')->expectsOutputToContain($message)->assertExitCode(1);

    expect(Permission::query()->count())->toBe(0);
})->with('unsupported spatie');
