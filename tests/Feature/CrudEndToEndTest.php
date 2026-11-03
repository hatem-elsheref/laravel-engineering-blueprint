<?php

use App\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    require_once __DIR__.'/../Fixtures/User.php';
    require_once __DIR__.'/../Fixtures/UserFactory.php';

    spl_autoload_register(function (string $class): void {
        $roots = ['App\\Modules\\' => app_path('Modules/'), 'Database\\Factories\\Shop\\' => database_path('factories/Shop/')];

        foreach ($roots as $prefix => $dir) {
            if (str_starts_with($class, $prefix)) {
                $file = $dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

                if (is_file($file)) {
                    require_once $file;
                }
            }
        }
    });

    config()->set('blueprint.profile', 'modular');
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);

    $this->artisan('make:module', ['name' => 'Shop', '--api' => true])->assertExitCode(0);
    $this->artisan('make:crud', [
        'module' => 'Shop',
        'entity' => 'PurchaseOrder',
        '--fields' => 'name:string,quantity:int,price:?float,active:bool,tags:array,shippedAt:?Carbon\CarbonImmutable',
    ])->assertExitCode(0);

    $this->loadLaravelMigrations();
    $this->artisan('migrate', ['--path' => glob(database_path('migrations/*_create_purchase_orders_table.php'))[0], '--realpath' => true])->assertExitCode(0);

    app()->register('App\Modules\Shop\Provider');
    $this->user = User::factory()->create();
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Shop'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    file_put_contents($this->providersPath, $this->providers);
});

function purchaseOrderPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Paper',
        'quantity' => 3,
        'price' => 9.5,
        'active' => true,
        'tags' => ['office'],
        'shipped_at' => '2026-11-01T10:00:00+00:00',
    ], $overrides);
}

it('denies guests', function () {
    $this->getJson('/api/purchase-orders')->assertForbidden();
});

it('lists purchase orders', function () {
    App\Modules\Shop\Models\PurchaseOrder::factory()->create(['name' => 'Listed']);

    $this->actingAs($this->user)->getJson('/api/purchase-orders')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Listed');
});

it('creates a purchase order', function () {
    $this->actingAs($this->user)->postJson('/api/purchase-orders', purchaseOrderPayload())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Paper')
        ->assertJsonPath('data.tags', ['office'])
        ->assertJsonPath('data.shipped_at', '2026-11-01T10:00:00+00:00');

    $this->assertDatabaseHas('purchase_orders', ['name' => 'Paper', 'quantity' => 3]);
});

it('accepts numeric strings', function () {
    $this->actingAs($this->user)->postJson('/api/purchase-orders', purchaseOrderPayload(['quantity' => '5', 'active' => '1']))
        ->assertCreated();

    $this->assertDatabaseHas('purchase_orders', ['name' => 'Paper', 'quantity' => 5, 'active' => true]);
});

it('validates required fields', function () {
    $this->actingAs($this->user)->postJson('/api/purchase-orders', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'quantity', 'active', 'tags'])
        ->assertJsonMissingValidationErrors(['price', 'shipped_at']);
});

it('shows, updates and deletes a purchase order', function () {
    $id = $this->actingAs($this->user)->postJson('/api/purchase-orders', purchaseOrderPayload())->json('data.id');

    $this->getJson("/api/purchase-orders/{$id}")->assertOk()->assertJsonPath('data.id', $id);

    $this->putJson("/api/purchase-orders/{$id}", purchaseOrderPayload(['name' => 'Ink', 'shipped_at' => null, 'price' => null]))
        ->assertOk()
        ->assertJsonPath('data.name', 'Ink')
        ->assertJsonPath('data.shipped_at', null);

    $this->deleteJson("/api/purchase-orders/{$id}")->assertNoContent();

    $this->assertDatabaseMissing('purchase_orders', ['id' => $id]);
});
