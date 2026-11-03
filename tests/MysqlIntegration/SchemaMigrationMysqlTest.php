<?php

use Best\Blueprint\Tests\MysqlIntegration\MysqlTestCase;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(MysqlTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('blueprint.profile', 'modular');
    config()->set('blueprint.module_path', 'app/Modules');

    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    $this->schema = dirname(__DIR__, 2).'/schemas/examples/orders/order.yaml';

    $this->artisan('make:module', ['name' => 'Orders', '--api' => true])->assertExitCode(0);
    $this->artisan('make:crud', [
        'module' => 'Orders',
        'entity' => 'Order',
        '--schema' => $this->schema,
    ])->assertExitCode(0);

    $this->ordersMigration = glob(database_path('migrations/*_create_orders_table.php'))[0] ?? null;
});

afterEach(function (): void {
    File::deleteDirectory(app_path('Modules'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Orders'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));

    if (isset($this->providersPath, $this->providers)) {
        file_put_contents($this->providersPath, $this->providers);
    }
});

it('migrates a schema dsl orders table on mysql', function (): void {
    expect($this->ordersMigration)->not->toBeNull();

    $this->artisan('migrate')->assertExitCode(0);

    expect(Schema::hasTable('orders'))->toBeTrue()
        ->and(Schema::hasColumn('orders', 'deleted_at'))->toBeTrue();

    DB::table('orders')->insert([
        'name' => 'Wire-1',
        'quantity' => 1,
        'tags' => '[]',
        'total' => '19.99',
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('orders')->insert([
        'name' => 'Wire-1',
        'quantity' => 1,
        'tags' => '[]',
        'total' => '9.99',
        'status' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
