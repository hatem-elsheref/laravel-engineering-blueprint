<?php

use Best\Blueprint\Tests\Fixtures\TestPermissions;
use Best\Blueprint\Tests\MysqlIntegration\MysqlSpatieTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(MysqlSpatieTestCase::class, RefreshDatabase::class);

beforeEach(function () {
    require_once __DIR__.'/../Fixtures/TestPermissions.php';
    config()->set('blueprint.profile', 'saas');
    config()->set('blueprint.permissions', TestPermissions::class);
});

it('writes permission rows on mysql', function (): void {
    $this->artisan('permissions:sync')->assertExitCode(0);

    expect(Permission::pluck('name')->sort()->values()->all())->toBe(['orders.view', 'products.update', 'products.view'])
        ->and(Role::pluck('name')->sort()->values()->all())->toBe(['editor', 'member', 'super-admin']);
});

it('is idempotent on mysql', function (): void {
    $this->artisan('permissions:sync')->assertExitCode(0);
    $this->artisan('permissions:sync')
        ->expectsOutputToContain('added 0 permission(s) and 0 role(s)')
        ->assertExitCode(0);

    expect(Permission::count())->toBe(3)->and(Role::count())->toBe(3);
});
