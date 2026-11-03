<?php

use Best\Blueprint\Tenancy\TenancyDetector;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    config()->set('app.url', 'http://localhost');
    File::put(database_path('migrations/0001_01_01_000000_create_users_table.php'), "<?php\n// users\n");
});

afterEach(function () {
    File::delete([
        config_path('tenancy.php'), config_path('blueprint.php'), base_path('routes/tenant.php'),
        app_path('Models/Tenant.php'), app_path('Providers/TenancyServiceProvider.php'),
    ]);
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(app_path('Shared'));
    file_put_contents($this->providersPath, $this->providers);
});

function installTenantSaas(): Illuminate\Testing\PendingCommand
{
    return test()->artisan('engineering:install', ['--profile' => 'tenant-saas', '--no-interaction' => true]);
}

it('sets up tenancy', function () {
    installTenantSaas()
        ->expectsOutputToContain('✓ ran tenancy:install')
        ->expectsOutputToContain('✓ created app/Models/Tenant.php')
        ->expectsOutputToContain('✓ copied users migration to database/migrations/tenant')
        ->expectsOutputToContain('✓ Tenancy')
        ->expectsOutputToContain("Your app's routes/web.php and routes/api.php answer on every domain")
        ->expectsOutputToContain('Next: php artisan migrate && php artisan tenant:create acme')
        ->doesntExpectOutputToContain('coming in Phase 6')
        ->assertExitCode(0);

    $config = file_get_contents(config_path('tenancy.php'));

    expect($config)->toContain("'tenant_model' => \\App\\Models\\Tenant::class,")
        ->toContain("'central_domains' => [\n        'localhost',\n        '127.0.0.1',\n    ],")
        ->and(file_get_contents(app_path('Models/Tenant.php')))->toBe(file_get_contents(__DIR__.'/../../stubs/tenant-model.stub'))
        ->and(file_get_contents(base_path('routes/tenant.php')))->not->toContain("Route::get('/'")->toContain("Route::middleware(['web', 'tenant'])")
        ->and(file_get_contents($this->providersPath))->toContain('App\Providers\TenancyServiceProvider::class')
        ->and(file_exists(database_path('migrations/tenant/0001_01_01_000000_create_users_table.php')))->toBeTrue();
});

it('is idempotent', function () {
    installTenantSaas()->assertExitCode(0);
    $config = file_get_contents(config_path('tenancy.php'));

    installTenantSaas()
        ->expectsOutputToContain('already set up: config/tenancy.php')
        ->expectsOutputToContain('already set up: app/Models/Tenant.php')
        ->expectsOutputToContain('already set up: tenant_model')
        ->expectsOutputToContain('already set up: central_domains')
        ->expectsOutputToContain('already set up: users migration')
        ->expectsOutputToContain('already set up: App\Providers\TenancyServiceProvider')
        ->assertExitCode(0);

    expect(file_get_contents(config_path('tenancy.php')))->toBe($config);
});

it('keeps an edited tenancy config', function () {
    File::put(config_path('tenancy.php'), "<?php\n\nreturn ['tenant_model' => My\\Tenant::class, 'central_domains' => ['x.test']];\n");

    installTenantSaas()
        ->expectsOutputToContain("Set 'tenant_model' => \\App\\Models\\Tenant::class in config/tenancy.php.")
        ->expectsOutputToContain("Check 'central_domains' in config/tenancy.php")
        ->assertExitCode(0);
});

it('explains missing stancl', function () {
    app()->instance(TenancyDetector::class, new class extends TenancyDetector
    {
        public function stancl(): bool
        {
            return false;
        }
    });

    installTenantSaas()
        ->expectsOutputToContain('Tenancy needs stancl/tenancy. Run: composer require stancl/tenancy:^3.10')
        ->expectsOutputToContain('✗ Tenancy (stancl/tenancy not installed)')
        ->expectsOutputToContain('Next: composer require stancl/tenancy:^3.10')
        ->doesntExpectOutputToContain('Next: php artisan make:module')
        ->assertExitCode(0);

    expect(file_get_contents(config_path('blueprint.php')))->toContain("'profile' => 'tenant-saas',")
        ->and(file_exists(config_path('tenancy.php')))->toBeFalse();
});

it('does nothing tenancy for other profiles', function () {
    $this->artisan('engineering:install', ['--profile' => 'saas', '--no-interaction' => true, '--allow-downgrade' => true])->assertExitCode(0);

    expect(file_exists(config_path('tenancy.php')))->toBeFalse();
});

it('warns when the cache store cannot isolate tenants', function (string $store) {
    config()->set('cache.default', $store);

    installTenantSaas()
        ->expectsOutputToContain("Tenant cache needs a cache store with tags (redis or memcached); CACHE_STORE={$store} fails inside tenants. Set CACHE_STORE=redis for tenant-saas.")
        ->assertExitCode(0);
})->with(['database', 'file']);

it('pins the database queue to the central connection', function () {
    installTenantSaas()
        ->expectsOutputToContain('✓ database queue uses the central connection (testing)')
        ->assertExitCode(0);
});
