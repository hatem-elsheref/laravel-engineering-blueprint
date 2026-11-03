<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    config()->set('blueprint.profile', 'modular');
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    $this->schema = dirname(__DIR__, 2).'/schemas/examples/orders/order.yaml';
    $this->artisan('make:module', ['name' => 'Orders', '--api' => true])->assertExitCode(0);
});

afterEach(function (): void {
    File::deleteDirectory(app_path('Modules'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Orders'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    file_put_contents($this->providersPath, $this->providers);
});

it('refuses schema and fields together', function (): void {
    $this->artisan('make:crud', [
        'module' => 'Orders',
        'entity' => 'Order',
        '--schema' => $this->schema,
        '--fields' => 'name:string',
    ])->expectsOutputToContain('Use --schema or --fields, not both.')
        ->assertExitCode(1);
});

it('generates enums migration and validation from schema', function (): void {
    $this->artisan('make:crud', [
        'module' => 'Orders',
        'entity' => 'Order',
        '--schema' => $this->schema,
    ])->assertExitCode(0);

    expect(file_exists(app_path('Modules/Orders/Enums/OrderStatus.php')))->toBeTrue();

    $model = file_get_contents(app_path('Modules/Orders/Models/Order.php'));
    expect($model)->toContain('use SoftDeletes;')
        ->and($model)->toContain("'status' => OrderStatus::class")
        ->and($model)->toContain('function scopePaid(Builder $query)')
        ->and($model)->toContain("'tags' => 'collection'")
        ->and($model)->toContain('function items():')
        ->and($model)->toContain('HasMany');

    $factory = file_get_contents(database_path('factories/Orders/OrderFactory.php'));
    expect($factory)->toContain("'quantity' => 1")
        ->and($factory)->toContain("'tags' => []");

    $migration = file_get_contents(glob(database_path('migrations/*_create_orders_table.php'))[0]);
    expect($migration)->toContain('$table->softDeletes();')
        ->and($migration)->toContain("->index(['status'])")
        ->and($migration)->toContain("\$table->string('name')->unique()")
        ->and($migration)->toContain("->decimal('total', 10, 2)")
        ->and($migration)->toContain("\$table->integer('quantity')->default(1)")
        ->and($migration)->toContain("\$table->json('tags')->default('[]')");

    $query = file_get_contents(app_path('Modules/Orders/Queries/ListOrders.php'));
    $resource = file_get_contents(app_path('Modules/Orders/Resources/OrderResource.php'));
    expect($query)->toContain('function handle(Request $request')
        ->and($query)->toContain("->where('status',")
        ->and($resource)->not->toContain("'note' =>");

    $store = file_get_contents(app_path('Modules/Orders/Requests/StoreOrderRequest.php'));
    $update = file_get_contents(app_path('Modules/Orders/Requests/UpdateOrderRequest.php'));
    expect($store)->toContain('Rule::enum(OrderStatus::class)')
        ->and($store)->toContain("'min:2'")
        ->and($store)->toContain("'max:120'")
        ->and($store)->toContain("'unique:orders,name'")
        ->and($update)->toContain("Rule::unique('orders', 'name')->ignore(\$this->route('order'))");

    foreach ([
        app_path('Modules/Orders/Models/Order.php'),
        app_path('Modules/Orders/Enums/OrderStatus.php'),
        app_path('Modules/Orders/Requests/StoreOrderRequest.php'),
    ] as $file) {
        $lint = new Process([PHP_BINARY, '-l', $file]);
        $lint->run();
        expect($lint->getExitCode())->toBe(0, $file.': '.$lint->getErrorOutput());
    }
});

it('refuses schema when adding a side', function (): void {
    $this->artisan('make:crud', [
        'module' => 'Orders',
        'entity' => 'Order',
        '--schema' => $this->schema,
    ])->assertExitCode(0);

    $this->artisan('make:crud', [
        'module' => 'Orders',
        'entity' => 'Order',
        '--web' => true,
        '--schema' => $this->schema,
    ])->expectsOutputToContain('Leave --schema out when adding a side')
        ->assertExitCode(1);
});
