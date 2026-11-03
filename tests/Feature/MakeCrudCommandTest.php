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
        ->expectsOutputToContain('Note: routes are protected by OrderPolicy (guests get 403). For token APIs add auth middleware (e.g. auth:sanctum) to the api routes in app/Modules/Orders/Provider.php, so every resource in this module gets it.')
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

function assertNothingWritten(object $test): void
{
    expect(file_get_contents(app_path('Modules/Orders/routes/api.php')))->toBe($test->routes)
        ->and(glob(database_path('migrations/*.php')))->toBe([])
        ->and(is_dir(app_path('Modules/Orders/Models')))->toBeFalse()
        ->and(is_dir(database_path('factories/Orders')))->toBeFalse()
        ->and(is_dir(base_path('tests/Feature/Modules')))->toBeFalse();
}

it('refuses project problems', function (string $key, string $value, string $message) {
    config()->set($key, $value);

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order'])
        ->expectsOutputToContain($message)
        ->assertExitCode(1);

    assertNothingWritten($this);
})->with([
    ['blueprint.profile', 'basic', 'The "basic" profile has no modules.'],
    ['blueprint.module_path', 'src/Modules', 'module_path must be inside app/ (got "src/Modules").'],
]);

it('refuses missing module', function () {
    $this->artisan('make:crud', ['module' => 'Billing', 'entity' => 'Invoice'])
        ->expectsOutputToContain('Module "Billing" not found in app/Modules.')
        ->assertExitCode(1);
});

it('refuses bad entity names', function (string $entity, string $message) {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => $entity])
        ->expectsOutputToContain($message)
        ->assertExitCode(1);

    assertNothingWritten($this);
})->with([
    ['1x', 'Invalid entity name "1x". Use a singular name, e.g. Order.'],
    ['Class', 'Invalid entity name "Class". Use a singular name, e.g. Order.'],
    ['Orders', 'Entity names must be singular, e.g. Order.'],
]);

it('normalizes the entity name', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'order'])
        ->expectsOutputToContain('Using entity name "Order".')
        ->assertExitCode(0);
});

it('refuses bad fields', function (string $fields, string $message) {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--fields' => $fields])
        ->expectsOutputToContain($message)
        ->assertExitCode(1);

    assertNothingWritten($this);
})->with([
    ['x:App\Status', 'Invalid field "x:App\Status": make:crud supports string, int, float, bool, array and Carbon\CarbonImmutable.'],
    ['id:int', 'Invalid field "id:int": id is created automatically.'],
    ['name:text', 'Invalid field "name:text": unknown type text.'],
]);

it('refuses a module without api routes', function () {
    $this->artisan('make:module', ['name' => 'Billing'])->assertExitCode(0);

    $this->artisan('make:crud', ['module' => 'Billing', 'entity' => 'Invoice'])
        ->expectsOutputToContain('Module "Billing" has no routes/api.php. Add it and load it in its Provider (see docs), or create the module with make:module Billing --api.')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Billing/Models')))->toBeFalse();
});

it('refuses collisions', function (callable $arrange, string $path) {
    $arrange();
    $routes = file_get_contents(app_path('Modules/Orders/routes/api.php'));

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order'])
        ->expectsOutputToContain("\"{$path}\" already exists. make:crud never overwrites files.")
        ->assertExitCode(1);

    expect(file_get_contents(app_path('Modules/Orders/routes/api.php')))->toBe($routes)
        ->and(is_dir(app_path('Modules/Orders/Controllers')))->toBeFalse();
})->with([
    'model' => [function () {
        File::ensureDirectoryExists(app_path('Modules/Orders/Models'));
        touch(app_path('Modules/Orders/Models/Order.php'));
    }, 'app/Modules/Orders/Models/Order.php'],
    'migration' => [function () {
        touch(database_path('migrations/2020_01_01_000000_create_orders_table.php'));
    }, 'database/migrations/2020_01_01_000000_create_orders_table.php'],
    'route' => [function () {
        file_put_contents(app_path('Modules/Orders/routes/api.php'), "// OrderController\n", FILE_APPEND);
    }, 'app/Modules/Orders/routes/api.php'],
]);

it('refuses old published stub', function () {
    File::ensureDirectoryExists(base_path('stubs/blueprint'));
    file_put_contents(base_path('stubs/blueprint/model.stub'), "<?php\n\nnamespace App\\Modules\\{{ moduleNamespace }}\\Models;\n\nfinal class {{ model }} {}\n");

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order'])
        ->expectsOutputToContain('did not produce a valid class (unknown placeholder {{ moduleNamespace }})')
        ->assertExitCode(1);

    assertNothingWritten($this);
});

it('rolls back on write failure', function () {
    file_put_contents(app_path('Modules/Orders/Resources'), 'not a folder');

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--fields' => CRUD_FIELDS])
        ->assertExitCode(1);

    expect(file_get_contents(app_path('Modules/Orders/routes/api.php')))->toBe($this->routes)
        ->and(glob(database_path('migrations/*.php')))->toBe([])
        ->and(is_dir(app_path('Modules/Orders/Models')))->toBeFalse()
        ->and(is_dir(database_path('factories/Orders')))->toBeFalse()
        ->and(is_dir(base_path('tests/Feature/Modules')))->toBeFalse()
        ->and(file_get_contents(app_path('Modules/Orders/Resources')))->toBe('not a folder');
});

it('does not confuse similar controller names', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'PurchaseOrder'])->assertExitCode(0);

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order'])->assertExitCode(0);

    expect(file_get_contents(app_path('Modules/Orders/routes/api.php')))
        ->toContain("Route::apiResource('orders', OrderController::class);");
});

it('refuses entity names that clash with imported classes', function (string $entity) {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => $entity])
        ->expectsOutputToContain("Entity name \"{$entity}\" clashes with a class the generated code uses")
        ->assertExitCode(1);

    assertNothingWritten($this);
})->with(['Response', 'Request', 'Model', 'Factory', 'Gate', 'Json']);

it('refuses module folders that are not namespace names', function () {
    File::ensureDirectoryExists(app_path('Modules/shared-utils/routes'));
    touch(app_path('Modules/shared-utils/routes/api.php'));

    $this->artisan('make:crud', ['module' => 'shared-utils', 'entity' => 'Thing'])
        ->expectsOutputToContain('Module folder "shared-utils" is not a valid PHP namespace name.')
        ->assertExitCode(1);
});

it('uses the route stub', function () {
    File::ensureDirectoryExists(base_path('stubs/blueprint'));
    file_put_contents(base_path('stubs/blueprint/route.stub'), "Route::apiResource('{{ uri }}', {{ controller }}::class)->middleware('auth:sanctum');\n");

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order'])->assertExitCode(0);

    expect(file_get_contents(app_path('Modules/Orders/routes/api.php')))
        ->toEndWith("Route::apiResource('orders', OrderController::class)->middleware('auth:sanctum');\n");
});

it('refuses a route stub without the controller', function () {
    File::ensureDirectoryExists(base_path('stubs/blueprint'));
    file_put_contents(base_path('stubs/blueprint/route.stub'), "Route::get('/{{ uri }}', fn () => 'x');\n");

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order'])
        ->expectsOutputToContain('did not produce a valid route (no OrderController::class)')
        ->assertExitCode(1);

    assertNothingWritten($this);
});

it('marks every policy method to tighten', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order'])->assertExitCode(0);

    expect(substr_count(file_get_contents(app_path('Modules/Orders/Policies/OrderPolicy.php')), '// Tighten for your app.'))->toBe(5);
});

it('generates guest checks for every endpoint', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order'])->assertExitCode(0);

    expect(file_get_contents(base_path('tests/Feature/Modules/Orders/OrderApiTest.php')))
        ->toContain("\$this->getJson('/api/orders')->assertForbidden();")
        ->toContain("\$this->postJson('/api/orders', Order::factory()->raw())->assertForbidden();")
        ->toContain("\$this->getJson(\"/api/orders/{\$order->id}\")->assertForbidden();")
        ->toContain("\$this->putJson(\"/api/orders/{\$order->id}\", Order::factory()->raw())->assertForbidden();")
        ->toContain("\$this->deleteJson(\"/api/orders/{\$order->id}\")->assertForbidden();");
});
