<?php

use Best\Blueprint\Tests\Fixtures\RoleUser;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($t) {
        $t->id();
        $t->string('name')->nullable();
        $t->timestamps();
    });
    Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
    Gate::define('update-thing', fn () => false);
    Gate::define('view-thing', fn () => true);
});

it('lets the super-admin pass any check, even a policy that denies', function () {
    $user = RoleUser::create(['name' => 'root']);
    $user->assignRole('super-admin');

    expect(Gate::forUser($user)->allows('anything'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update-thing'))->toBeTrue();
});

it('leaves other users to their policies', function () {
    $user = RoleUser::create(['name' => 'plain']);

    expect(Gate::forUser($user)->allows('anything'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('update-thing'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('view-thing'))->toBeTrue();
});

it('does not error for a user model without roles', function () {
    $user = new class extends Authenticatable {};

    expect(Gate::forUser($user)->allows('anything'))->toBeFalse();
});
