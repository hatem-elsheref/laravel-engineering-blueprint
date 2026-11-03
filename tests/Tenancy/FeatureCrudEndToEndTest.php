<?php

use App\Models\User;
use Best\Blueprint\Facades\Setting;
use Best\Blueprint\Features\FeatureAdmin;
use Best\Blueprint\Settings\SettingAdmin;
use Best\Blueprint\Tenancy\TenantSetup;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

// Module names here must not be used by other test files: generated classes stay loaded for the whole run.
beforeEach(function () {
    useTestFlags();
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);

    spl_autoload_register(function (string $class): void {
        $roots = ['App\\Modules\\' => app_path('Modules/'), 'Database\\Factories\\Depot\\' => database_path('factories/Depot/')];

        foreach ($roots as $prefix => $dir) {
            if (str_starts_with($class, $prefix) && is_file($file = $dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php')) {
                require_once $file;
            }
        }
    });

    File::ensureDirectoryExists(database_path('migrations/tenant'));
    File::copy(__DIR__.'/../../vendor/orchestra/testbench-core/laravel/migrations/0001_01_01_000000_testbench_create_users_table.php', database_path('migrations/tenant/0001_01_01_000000_create_users_table.php'));
    File::copy(__DIR__.'/../../stubs/features-migration.stub', database_path('migrations/tenant/2026_01_01_000100_create_features_table.php'));
    File::copy(__DIR__.'/../../stubs/settings-migration.stub', database_path('migrations/tenant/2026_01_01_000200_create_settings_table.php'));

    $this->artisan('make:module', ['name' => 'Depot', '--api' => true])->assertExitCode(0);
    $this->artisan('make:crud', ['module' => 'Depot', 'entity' => 'Crate', '--open-policy' => true, '--fields' => 'name:string', '--feature' => 'reports'])->assertExitCode(0);
    app(TenantSetup::class)->run('a', 'a.localhost', 'tenant_a');
    app()->register('App\Modules\Depot\Provider');
    $this->user = tenancy()->find('a')->run(fn () => User::factory()->create());
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::deleteDirectory(database_path('migrations/tenant'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Depot'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    file_put_contents($this->providersPath, $this->providers);
});

it('blocks a tenant crud while its feature is disabled', function () {
    $this->actingAs($this->user)->getJson('http://a.localhost/api/crates')->assertForbidden();
    $this->actingAs($this->user)->postJson('http://a.localhost/api/crates', ['name' => 'Box'])->assertForbidden();

    tenancy()->find('a')->run(fn () => app(FeatureAdmin::class)->enable('reports'));
    app()->forgetScopedInstances(); // a new request

    $this->actingAs($this->user)->postJson('http://a.localhost/api/crates', ['name' => 'Box'])->assertCreated();
    $this->actingAs($this->user)->getJson('http://a.localhost/api/crates')->assertOk()->assertJsonCount(1, 'data');
});

it('reads tenant settings during a tenant request', function () {
    tenancy()->find('a')->run(fn () => app(SettingAdmin::class)->set('orders.limit', 42));
    Route::middleware(['api', 'tenant'])->get('/limit', fn () => ['limit' => Setting::get('orders.limit')]);

    $this->getJson('http://a.localhost/limit')->assertOk()->assertJsonPath('limit', 42);
});
