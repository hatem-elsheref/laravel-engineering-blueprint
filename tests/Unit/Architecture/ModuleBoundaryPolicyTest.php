<?php

declare(strict_types=1);

use Best\Blueprint\Architecture\ModuleBoundaryPolicy;

it('detects owner module from app paths', function (): void {
    $policy = ModuleBoundaryPolicy::fromBlueprintConfig('App\\Modules', 'app/Modules', [
        'forbidden_cross_module_segments' => ['Models'],
        'allowed_cross_module_segments' => ['Actions'],
    ]);

    $path = '/var/www/app/Modules/Orders/Actions/Ship.php';

    expect($policy->ownerModuleFromAbsolutePath($path))->toBe('Orders')
        ->and($policy->ownerModuleFromModuleRootRelativePath('Orders/Actions/Ship.php'))->toBe('Orders');
});

it('flags forbidden cross-module model references', function (): void {
    $policy = ModuleBoundaryPolicy::fromBlueprintConfig('App\\Modules', 'app/Modules', [
        'forbidden_cross_module_segments' => ['Models'],
        'allowed_cross_module_segments' => ['Actions'],
    ]);

    expect($policy->violationReason('Orders', 'App\\Modules\\Inventory\\Models\\Product'))
        ->toContain('Cross-module Models reference')
        ->and($policy->violationReason('Orders', 'App\\Modules\\Inventory\\Actions\\ReserveStock'))->toBeNull();
});
