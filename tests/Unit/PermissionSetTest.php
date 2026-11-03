<?php

use Best\Blueprint\Permissions\InvalidPermissionDefinition;
use Best\Blueprint\Permissions\PermissionSet;
use Best\Blueprint\Tests\Fixtures\TestPermissions;

it('expands groups into names', function () {
    expect(PermissionSet::from(['products' => ['view', 'update']], [])->permissions())
        ->toBe(['products.update', 'products.view']);
});

it('expands wildcards', function () {
    $set = PermissionSet::from(
        ['products' => ['view', 'update'], 'orders' => ['view']],
        [
            'admin' => ['*'],
            'editor' => ['products.*'],
            'viewer' => ['products.view', 'orders.view'],
        ],
    );

    expect($set->roles())->toBe([
        'admin' => ['orders.view', 'products.update', 'products.view'],
        'editor' => ['products.update', 'products.view'],
        'viewer' => ['orders.view', 'products.view'],
    ]);
});

it('rejects a pattern that matches nothing', function () {
    PermissionSet::from(['products' => ['view']], ['editor' => ['prodcts.view']]);
})->throws(InvalidPermissionDefinition::class, 'prodcts.view');

it('names the role when a pattern matches nothing', function () {
    PermissionSet::from(['products' => ['view']], ['editor' => ['prodcts.view']]);
})->throws(InvalidPermissionDefinition::class, 'editor');

it('rejects a group wildcard for an unknown group', function () {
    PermissionSet::from(['products' => ['view']], ['x' => ['nope.*']]);
})->throws(InvalidPermissionDefinition::class);

it('rejects invalid names', function (array $groups) {
    PermissionSet::from($groups, []);
})->with([
    'group' => [['Products' => ['view']]],
    'ability' => [['products' => ['Update']]],
    'double dash' => [['products' => ['a..b']]],
])->throws(InvalidPermissionDefinition::class);

it('rejects the super-admin role in roles()', function () {
    config()->set('blueprint.super_admin', 'super-admin');

    PermissionSet::from(['products' => ['view']], ['super-admin' => ['*']]);
})->throws(InvalidPermissionDefinition::class, 'built in');

it('allows a role with no permissions', function () {
    expect(PermissionSet::from(['products' => ['view']], ['member' => []])->roles())->toBe(['member' => []]);
});

it('reads a definitions class', function () {
    $set = PermissionSet::fromClass(TestPermissions::class);

    expect($set->permissions())->toBe(['orders.view', 'products.update', 'products.view'])
        ->and($set->roles())->toBe(['editor' => ['products.update', 'products.view'], 'member' => []]);

    PermissionSet::fromClass('Missing\\Class');
})->throws(InvalidPermissionDefinition::class);
