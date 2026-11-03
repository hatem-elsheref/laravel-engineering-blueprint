<?php

use App\Models\User;
use Best\Blueprint\Tenancy\TenantSetup;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

// Module names here must not be used by other test files: generated classes stay loaded for the whole run.
beforeEach(function () {
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);

    spl_autoload_register(function (string $class): void {
        $roots = ['App\\Modules\\' => app_path('Modules/'), 'Database\\Factories\\Store\\' => database_path('factories/Store/')];

        foreach ($roots as $prefix => $dir) {
            if (str_starts_with($class, $prefix) && is_file($file = $dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php')) {
                require_once $file;
            }
        }
    });

    File::ensureDirectoryExists(database_path('migrations/tenant'));
    File::copy(
        __DIR__.'/../../vendor/orchestra/testbench-core/laravel/migrations/0001_01_01_000000_testbench_create_users_table.php',
        database_path('migrations/tenant/0001_01_01_000000_create_users_table.php'),
    );
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::deleteDirectory(database_path('migrations/tenant'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Store'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    file_put_contents($this->providersPath, $this->providers);
});

it('same uri resolves per host', function () {
    $this->artisan('make:module', ['name' => 'Platform', '--api' => true, '--central' => true])->assertExitCode(0);
    $this->artisan('make:module', ['name' => 'Store', '--api' => true])->assertExitCode(0);
    File::append(app_path('Modules/Platform/routes/api.php'), "Route::get('/ping', fn () => 'central');\n");
    File::append(app_path('Modules/Store/routes/api.php'), "Route::get('/ping', fn () => 'tenant:'.tenant('id'));\n");
    app(TenantSetup::class)->run('a', 'a.localhost', 'tenant_a');

    app()->register('App\Modules\Platform\Provider');
    app()->register('App\Modules\Store\Provider');

    $this->get('http://localhost/api/ping')->assertOk()->assertSee('central');
    $this->get('http://a.localhost/api/ping')->assertOk()->assertSee('tenant:a');
});

it('serves a generated tenant crud', function () {
    $this->artisan('make:module', ['name' => 'Store', '--api' => true])->assertExitCode(0);
    $this->artisan('make:crud', ['module' => 'Store', 'entity' => 'Product', '--open-policy' => true, '--fields' => 'name:string'])->assertExitCode(0);
    app(TenantSetup::class)->run('a', 'a.localhost', 'tenant_a');
    app()->register('App\Modules\Store\Provider');

    $user = tenancy()->find('a')->run(fn () => User::factory()->create());

    $this->getJson('http://a.localhost/api/products')->assertForbidden();
    $this->getJson('http://a.localhost/api/products/999')->assertForbidden();

    $id = $this->actingAs($user)->postJson('http://a.localhost/api/products', ['name' => 'Desk'])->assertCreated()->json('data.id');
    $this->actingAs($user)->getJson("http://a.localhost/api/products/{$id}")->assertOk()->assertJsonPath('data.name', 'Desk');
    $this->actingAs($user)->getJson('http://a.localhost/api/products')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($user)->deleteJson("http://a.localhost/api/products/{$id}")->assertNoContent();

    $this->getJson('http://localhost/api/products')->assertNotFound();
    expect(Schema::connection('testing')->hasTable('products'))->toBeFalse();
});

it('does not expose another tenant crud rows by id', function () {
    $this->artisan('make:module', ['name' => 'Store', '--api' => true])->assertExitCode(0);
    $this->artisan('make:crud', ['module' => 'Store', 'entity' => 'Product', '--open-policy' => true, '--fields' => 'name:string'])->assertExitCode(0);

    app(TenantSetup::class)->run('a', 'a.localhost', 'tenant_a');
    app(TenantSetup::class)->run('b', 'b.localhost', 'tenant_b');
    app()->register('App\Modules\Store\Provider');

    $userA = tenancy()->find('a')->run(fn () => User::factory()->create());
    $idInA = $this->actingAs($userA)->postJson('http://a.localhost/api/products', ['name' => 'Tenant-A-Only'])->assertCreated()->json('data.id');

    $userB = tenancy()->find('b')->run(fn () => User::factory()->create());
    $this->actingAs($userB)->getJson('http://b.localhost/api/products')->assertOk()->assertJsonCount(0, 'data');
    $this->actingAs($userB)->getJson("http://b.localhost/api/products/{$idInA}")->assertNotFound();
});

it('caches routes when a central and a tenant module share a resource', function () {
    $this->artisan('make:module', ['name' => 'Platform', '--api' => true, '--central' => true])->assertExitCode(0);
    $this->artisan('make:module', ['name' => 'Store', '--api' => true])->assertExitCode(0);
    File::append(app_path('Modules/Platform/routes/api.php'), "Route::get('/items', fn () => 'c')->name('items.index');\n");
    File::append(app_path('Modules/Store/routes/api.php'), "Route::get('/items', fn () => 't')->name('items.index');\n");
    app()->register('App\Modules\Platform\Provider');
    app()->register('App\Modules\Store\Provider');

    app('router')->getRoutes()->refreshNameLookups(); // what Laravel does after booting the app's routes
    $names = array_keys(app('router')->getRoutes()->getRoutesByName());

    expect($names)->toContain('central.items.index')->toContain('items.index')
        ->and(route('central.items.index'))->toBe('http://localhost/api/items');
});
