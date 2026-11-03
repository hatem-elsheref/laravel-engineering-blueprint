<?php

use Best\Blueprint\Support\PestDetector;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config()->set('blueprint.profile', 'modular');
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    $this->artisan('make:module', ['name' => 'Orders', '--api' => true])->assertExitCode(0);
    $this->routes = file_get_contents(app_path('Modules/Orders/routes/api.php'));
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Orders'));
    File::deleteDirectory(database_path('factories/Billing'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    File::deleteDirectory(base_path('stubs'));
    file_put_contents($this->providersPath, $this->providers);
});

const CRUD_FIELDS = 'name:string,quantity:int,note:?string';

function crudFiles(): array
{
    $migrations = glob(database_path('migrations/*_create_orders_table.php'));

    return [
        'app/Modules/Orders/Models/Order.php',
        $migrations === [] ? 'database/migrations/missing' : 'database/migrations/'.basename($migrations[0]),
        'database/factories/Orders/OrderFactory.php',
        'app/Modules/Orders/Requests/StoreOrderRequest.php',
        'app/Modules/Orders/Requests/UpdateOrderRequest.php',
        'app/Modules/Orders/Data/OrderData.php',
        'app/Modules/Orders/Actions/CreateOrder.php',
        'app/Modules/Orders/Actions/UpdateOrder.php',
        'app/Modules/Orders/Actions/DeleteOrder.php',
        'app/Modules/Orders/Queries/ListOrders.php',
        'app/Modules/Orders/Controllers/OrderController.php',
        'app/Modules/Orders/Resources/OrderResource.php',
        'app/Modules/Orders/Policies/OrderPolicy.php',
        'tests/Feature/Modules/Orders/OrderApiTest.php',
    ];
}

it('generates every file', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--fields' => CRUD_FIELDS])
        ->expectsOutputToContain('created app/Modules/Orders/Models/Order.php')
        ->expectsOutputToContain('created tests/Feature/Modules/Orders/OrderApiTest.php')
        ->expectsOutputToContain('added route to app/Modules/Orders/routes/api.php')
        ->expectsOutputToContain('Note: routes are protected by OrderPolicy (guests get 403). Add auth middleware (e.g. auth:sanctum) in routes/api.php for token APIs.')
        ->expectsOutputToContain('Next: php artisan migrate && php artisan test --filter=OrderApiTest')
        ->assertExitCode(0)
        ->run();

    foreach (crudFiles() as $file) {
        expect(file_exists(base_path($file)))->toBeTrue($file);
    }
});

it('matches golden files', function (string $fixture, string $path) {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--fields' => CRUD_FIELDS])->assertExitCode(0);

    expect(file_get_contents(base_path($path)))->toBe(file_get_contents(__DIR__.'/../Fixtures/crud/'.$fixture));
})->with([
    ['Order.php', 'app/Modules/Orders/Models/Order.php'],
    ['OrderData.php', 'app/Modules/Orders/Data/OrderData.php'],
    ['StoreOrderRequest.php', 'app/Modules/Orders/Requests/StoreOrderRequest.php'],
    ['OrderController.php', 'app/Modules/Orders/Controllers/OrderController.php'],
]);

it('wires the route', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--fields' => CRUD_FIELDS])->assertExitCode(0);

    expect(file_get_contents(app_path('Modules/Orders/routes/api.php')))
        ->toContain("use App\\Modules\\Orders\\Controllers\\OrderController;\n")
        ->toEndWith("Route::apiResource('orders', OrderController::class);\n");
});

it('adds a second entity', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--fields' => CRUD_FIELDS])->assertExitCode(0);
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Invoice', '--fields' => 'number:string'])->assertExitCode(0);

    expect(file_get_contents(app_path('Modules/Orders/routes/api.php')))
        ->toContain('use App\Modules\Orders\Controllers\OrderController;')
        ->toContain('use App\Modules\Orders\Controllers\InvoiceController;')
        ->toContain("Route::apiResource('orders', OrderController::class);")
        ->toEndWith("Route::apiResource('invoices', InvoiceController::class);\n");
});

it('generates with no fields', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order'])->assertExitCode(0);

    $migration = file_get_contents(glob(database_path('migrations/*_create_orders_table.php'))[0]);

    expect(file_get_contents(app_path('Modules/Orders/Models/Order.php')))->not->toContain('casts()')
        ->and($migration)->toContain("            \$table->id();\n            \$table->timestamps();\n");
});

it('generated files are valid php', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--fields' => CRUD_FIELDS.',price:?float,active:bool,tags:array,shippedAt:?Carbon\CarbonImmutable'])->assertExitCode(0);

    foreach ([...crudFiles(), 'app/Modules/Orders/routes/api.php'] as $file) {
        $lint = new Symfony\Component\Process\Process([PHP_BINARY, '-l', base_path($file)]);
        $lint->run();
        expect($lint->getExitCode())->toBe(0, $file.': '.$lint->getOutput());
    }
});

it('skips tests without pest', function () {
    app()->instance(PestDetector::class, new class extends PestDetector
    {
        public function installed(): bool
        {
            return false;
        }
    });

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--fields' => CRUD_FIELDS])
        ->expectsOutputToContain('Skipped tests/Feature/Modules/Orders/OrderApiTest.php: Pest is not installed')
        ->assertExitCode(0);

    expect(file_exists(base_path('tests/Feature/Modules/Orders/OrderApiTest.php')))->toBeFalse();
});
