<?php

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    config()->set('blueprint.profile', 'modular');
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    $this->artisan('make:module', ['name' => 'Orders', '--api' => true])->assertExitCode(0);
    $this->artisan('make:crud', [
        'module' => 'Orders',
        'entity' => 'Order',
        '--open-policy' => true,
    ])->assertExitCode(0);
});

afterEach(function (): void {
    File::deleteDirectory(app_path('Modules'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Orders'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    file_put_contents($this->providersPath, $this->providers);
});

it('registers nested api routes and scopes the list query', function (): void {
    $this->artisan('make:nested-crud', [
        'module' => 'Orders',
        'parent' => 'Order',
        'entity' => 'OrderLine',
        '--fields' => 'sku:string,quantity:int',
        '--open-policy' => true,
    ])->assertExitCode(0);

    $routes = file_get_contents(app_path('Modules/Orders/routes/api.php'));
    $query = file_get_contents(app_path('Modules/Orders/Queries/ListOrderLines.php'));

    expect($routes)->toContain("Route::apiResource('orders.order-lines'")
        ->and($routes)->toContain("->names('api.orders.order-lines')")
        ->and($query)->toContain("->where('order_id', \$request->route('order'))");
});
