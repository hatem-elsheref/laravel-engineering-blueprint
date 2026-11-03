<?php

use Best\Blueprint\Testing\InteractsWithPermissions;
use Best\Blueprint\Tests\Fixtures\RoleUser;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

uses(InteractsWithPermissions::class);

beforeEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($t) {
        $t->id();
        $t->string('name')->nullable();
        $t->timestamps();
    });
});

it('grants exactly the given permissions', function () {
    $user = RoleUser::create(['name' => 'a']);
    $this->grantPermissions($user, 'products.view', 'products.create');

    expect($user->checkPermissionTo('products.view'))->toBeTrue()
        ->and($user->checkPermissionTo('products.create'))->toBeTrue()
        ->and(fn () => $user->checkPermissionTo('products.delete'))->not->toThrow(Throwable::class)
        ->and(Gate::forUser($user)->allows('products.delete'))->toBeFalse();
});

it('is not served stale data after a second grant to the same user', function () {
    $user = RoleUser::create(['name' => 'a']);
    $this->grantPermissions($user, 'a.view');
    expect($user->can('a.view'))->toBeTrue();

    $this->grantPermissions($user, 'b.view');
    expect($user->can('b.view'))->toBeTrue();
});

it('reuses one role for the same set in any order', function () {
    $this->grantPermissions(RoleUser::create(['name' => 'a']), 'x.view', 'x.edit');
    $this->grantPermissions(RoleUser::create(['name' => 'b']), 'x.edit', 'x.view');

    expect(Role::count())->toBe(1);
});

it('makes a super-admin pass any gate check', function () {
    $user = RoleUser::create(['name' => 'root']);
    $this->makeSuperAdmin($user);
    $this->makeSuperAdmin($user);

    expect(Gate::forUser($user)->allows('x'))->toBeTrue()
        ->and(Role::where('name', 'super-admin')->count())->toBe(1);
});
