<?php

declare(strict_types=1);

use Best\Blueprint\Architecture\ModuleBoundaryScanner;
use Best\Blueprint\PhpStan\ModuleBoundaryPolicyFactory;
use Best\Blueprint\Support\Modules;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    config()->set('blueprint.profile', 'modular');
    config()->set('blueprint.module_path', 'app/Modules');
    $this->root = base_path('app/Modules');
    File::deleteDirectory($this->root);
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
});

it('flags cross-module model imports', function (): void {
    File::ensureDirectoryExists($this->root.'/Orders/Actions');
    file_put_contents($this->root.'/Orders/Actions/Ship.php', <<<'PHP'
<?php
namespace App\Modules\Orders\Actions;
use App\Modules\Inventory\Models\Product;
final class Ship {}
PHP);

    $violations = (new ModuleBoundaryScanner(new Modules, ModuleBoundaryPolicyFactory::fromApplication(new Modules)))->scan($this->root);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->ownerModule)->toBe('Orders')
        ->and($violations[0]->otherModule)->toBe('Inventory')
        ->and($violations[0]->import)->toBe('App\Modules\Inventory\Models\Product');
});

it('allows cross-module action imports', function (): void {
    File::ensureDirectoryExists($this->root.'/Orders/Actions');
    file_put_contents($this->root.'/Orders/Actions/Place.php', <<<'PHP'
<?php
namespace App\Modules\Orders\Actions;
use App\Modules\Inventory\Actions\ReserveStock;
final class Place {}
PHP);

    $violations = (new ModuleBoundaryScanner(new Modules, ModuleBoundaryPolicyFactory::fromApplication(new Modules)))->scan($this->root);

    expect($violations)->toBeEmpty();
});

it('ignores same-module imports', function (): void {
    File::ensureDirectoryExists($this->root.'/Orders/Actions');
    file_put_contents($this->root.'/Orders/Actions/Create.php', <<<'PHP'
<?php
namespace App\Modules\Orders\Actions;
use App\Modules\Orders\Models\Order;
final class Create {}
PHP);

    $violations = (new ModuleBoundaryScanner(new Modules, ModuleBoundaryPolicyFactory::fromApplication(new Modules)))->scan($this->root);

    expect($violations)->toBeEmpty();
});
