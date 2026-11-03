<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

beforeEach(function () {
    config()->set('blueprint.profile', 'modular');
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    $this->artisan('make:module', ['name' => 'Orders', '--api' => true, '--web' => true])->assertExitCode(0);
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Orders'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    File::deleteDirectory(resource_path('views/orders'));
    File::deleteDirectory(resource_path('views/layouts'));
    File::deleteDirectory(base_path('stubs'));
    file_put_contents($this->providersPath, $this->providers);
});

const WEB_FIELDS = 'name:string,quantity:int,note:?string';

function webFiles(): array
{
    return [
        'app/Modules/Orders/Controllers/OrderPageController.php',
        'resources/views/orders/orders/index.blade.php',
        'resources/views/orders/orders/create.blade.php',
        'resources/views/orders/orders/edit.blade.php',
        'resources/views/orders/orders/show.blade.php',
        'resources/views/orders/orders/_form.blade.php',
        'tests/Feature/Modules/Orders/OrderWebTest.php',
    ];
}

function apiOnlyFiles(): array
{
    return [
        'app/Modules/Orders/Controllers/OrderController.php',
        'app/Modules/Orders/Resources/OrderResource.php',
        'tests/Feature/Modules/Orders/OrderApiTest.php',
    ];
}

it('writes the right files for each side', function (array $flags, bool $web, bool $api) {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--fields' => WEB_FIELDS] + $flags)->assertExitCode(0);

    expect(file_exists(app_path('Modules/Orders/Models/Order.php')))->toBeTrue();

    foreach (webFiles() as $file) {
        expect(file_exists(base_path($file)))->toBe($web, $file);
    }

    foreach (apiOnlyFiles() as $file) {
        expect(file_exists(base_path($file)))->toBe($api, $file);
    }
})->with([
    'default is api' => [[], false, true],
    'api' => [['--api' => true], false, true],
    'web' => [['--web' => true], true, false],
    'both' => [['--api' => true, '--web' => true], true, true],
]);

it('matches the web golden files', function (string $fixture, string $path) {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--fields' => WEB_FIELDS, '--web' => true])->assertExitCode(0);

    expect(file_get_contents(base_path($path)))->toBe(file_get_contents(__DIR__.'/../Fixtures/crud/'.$fixture));
})->with([
    ['OrderPageController.php', 'app/Modules/Orders/Controllers/OrderPageController.php'],
    ['_form.blade.php', 'resources/views/orders/orders/_form.blade.php'],
]);

it('wires the web route', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--web' => true])
        ->expectsOutputToContain('added route to app/Modules/Orders/routes/web.php')
        ->assertExitCode(0);

    expect(file_get_contents(app_path('Modules/Orders/routes/web.php')))
        ->toContain("use App\\Modules\\Orders\\Controllers\\OrderPageController;\n")
        ->toEndWith("Route::resource('orders', OrderPageController::class)\n    ->missing(fn (\$request) => abort(\$request->user() === null ? 403 : 404));\n");
});

it('creates the layout once', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--web' => true])
        ->expectsOutputToContain('created resources/views/layouts/blueprint.blade.php')
        ->assertExitCode(0);
    file_put_contents(resource_path('views/layouts/blueprint.blade.php'), 'custom');

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Invoice', '--web' => true])->assertExitCode(0);

    expect(file_get_contents(resource_path('views/layouts/blueprint.blade.php')))->toBe('custom');
});

it('generates valid php and blade', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--web' => true, '--api' => true, '--fields' => WEB_FIELDS.',price:?float,active:bool,tags:array,shippedAt:?Carbon\CarbonImmutable'])->assertExitCode(0);

    foreach (['app/Modules/Orders/Controllers/OrderPageController.php', 'tests/Feature/Modules/Orders/OrderWebTest.php', 'app/Modules/Orders/routes/web.php'] as $file) {
        $lint = new Process([PHP_BINARY, '-l', base_path($file)]);
        $lint->run();
        expect($lint->getExitCode())->toBe(0, $file.$lint->getOutput());
    }

    foreach (glob(resource_path('views/orders/orders/*.blade.php')) as $view) {
        $compiled = Blade::compileString(file_get_contents($view));
        $lint = new Process([PHP_BINARY, '-l']);
        $lint->setInput($compiled);
        $lint->run();
        expect($lint->getExitCode())->toBe(0, $view.$lint->getOutput());
    }
});

it('adds web pages to an existing api crud', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--fields' => WEB_FIELDS])->assertExitCode(0);
    $model = file_get_contents(app_path('Modules/Orders/Models/Order.php'));

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--fields' => WEB_FIELDS, '--web' => true])
        ->expectsOutputToContain('added web pages to the existing Order CRUD')
        ->assertExitCode(0);

    expect(file_get_contents(app_path('Modules/Orders/Models/Order.php')))->toBe($model)
        ->and(glob(database_path('migrations/*_create_orders_table.php')))->toHaveCount(1);

    foreach (webFiles() as $file) {
        expect(file_exists(base_path($file)))->toBeTrue($file);
    }
});

it('adds the api to an existing web crud', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--web' => true])->assertExitCode(0);

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--api' => true])
        ->expectsOutputToContain('added the API to the existing Order CRUD')
        ->assertExitCode(0);

    foreach (apiOnlyFiles() as $file) {
        expect(file_exists(base_path($file)))->toBeTrue($file);
    }
});

it('refuses a side that already exists', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--web' => true])->assertExitCode(0);

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--web' => true])
        ->expectsOutputToContain('"app/Modules/Orders/Controllers/OrderPageController.php" already exists.')
        ->assertExitCode(1);
});

it('refuses --web without web routes', function () {
    $this->artisan('make:module', ['name' => 'Billing', '--api' => true])->assertExitCode(0);

    $this->artisan('make:crud', ['module' => 'Billing', 'entity' => 'Invoice', '--web' => true])
        ->expectsOutputToContain('Module "Billing" has no routes/web.php. Add it and load it in its Provider (see docs), or create the module with make:module Billing --web.')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Billing/Models')))->toBeFalse();
    File::deleteDirectory(database_path('factories/Billing'));
});

it('refuses a taken web route name', function () {
    Route::get('/old', fn () => 'x')->name('orders.index');
    app('router')->getRoutes()->refreshNameLookups();

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--web' => true])
        ->expectsOutputToContain('Route name "orders.index" is already used')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Models')))->toBeFalse();
});
