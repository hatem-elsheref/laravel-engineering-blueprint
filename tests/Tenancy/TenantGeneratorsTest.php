<?php

use Best\Blueprint\Tenancy\TenancyDetector;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

beforeEach(function () {
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Orders'));
    File::deleteDirectory(database_path('factories/Admin'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    file_put_contents($this->providersPath, $this->providers);
});

const TENANT_PROVIDER = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class Provider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        // Generated API routes use the 'api' middleware only — add auth (e.g. auth:sanctum) before production (docs/SECURITY-GENERATION.md).

        // Registered after central routes, so central (domain-bound) routes win on central hosts.
        $this->app->booted(function (): void {
            // Add auth (e.g. auth:sanctum) on tenant API routes before production (docs/SECURITY-GENERATION.md).
            Route::middleware(['web', 'tenant'])->group(__DIR__.'/routes/web.php');
            Route::middleware(['api', 'tenant'])->prefix('api')->group(__DIR__.'/routes/api.php');
        });
    }
}

PHP;

it('generates tenant module providers', function () {
    $this->artisan('make:module', ['name' => 'Orders', '--api' => true, '--web' => true])->assertExitCode(0);

    expect(file_get_contents(app_path('Modules/Orders/Provider.php')))->toBe(TENANT_PROVIDER);
});

it('generates central module providers', function () {
    $this->artisan('make:module', ['name' => 'Admin', '--api' => true, '--central' => true])->assertExitCode(0);

    expect(file_get_contents(app_path('Modules/Admin/Provider.php')))
        ->toContain("\$domains = implode('|', array_map('preg_quote', config('tenancy.central_domains', [])));")
        ->toContain("Route::domain('{central_domain}')->where(['central_domain' => \$domains])->middleware(['api', 'central'])->prefix('api')->name('central.')->group(__DIR__.'/routes/api.php');")
        ->not->toContain('booted')
        ->not->toContain("'web', 'central'");
});

it('refuses --central outside tenant-saas', function () {
    config()->set('blueprint.profile', 'modular');

    $this->artisan('make:module', ['name' => 'Admin', '--central' => true])
        ->expectsOutputToContain('--central only applies to the tenant-saas profile.')
        ->assertExitCode(1);
});

it('refuses tenant-saas modules without stancl', function () {
    app()->instance(TenancyDetector::class, new class extends TenancyDetector
    {
        public function stancl(): bool
        {
            return false;
        }
    });

    $this->artisan('make:module', ['name' => 'Orders'])
        ->expectsOutputToContain('Tenancy needs stancl/tenancy. Run: composer require stancl/tenancy:^3.10')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules')))->toBeFalse();
});

it('puts tenant crud migrations in the tenant folder', function () {
    $this->artisan('make:module', ['name' => 'Orders', '--api' => true])->assertExitCode(0);
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--fields' => 'name:string'])->assertExitCode(0);

    expect(glob(database_path('migrations/tenant/*_create_orders_table.php')))->toHaveCount(1)
        ->and(glob(database_path('migrations/*_create_orders_table.php')))->toBe([])
        ->and(file_get_contents(base_path('tests/Feature/Modules/Orders/OrderApiTest.php')))
        ->toContain('uses(InteractsWithTenancy::class, DatabaseMigrations::class, Best\\Blueprint\\Testing\\ActsAsApiUser::class);')
        ->toContain("\$this->tenantUrl('/api/orders')")
        ->toContain('denies authenticated users until OrderPolicy allows access')
        ->toContain('$this->tenant = $this->createTestTenant();');

    $lint = new Process([PHP_BINARY, '-l', base_path('tests/Feature/Modules/Orders/OrderApiTest.php')]);
    $lint->run();
    expect($lint->getExitCode())->toBe(0, $lint->getOutput());
});

it('keeps central crud on the central side', function () {
    $this->artisan('make:module', ['name' => 'Admin', '--api' => true, '--central' => true])->assertExitCode(0);
    $this->artisan('make:crud', ['module' => 'Admin', 'entity' => 'Plan', '--fields' => 'name:string'])->assertExitCode(0);

    expect(glob(database_path('migrations/*_create_plans_table.php')))->toHaveCount(1)
        ->and(file_get_contents(base_path('tests/Feature/Modules/Admin/PlanApiTest.php')))->not->toContain('InteractsWithTenancy');

    File::deleteDirectory(database_path('factories/Admin'));
});

it('checks tenant migrations for collisions', function () {
    $this->artisan('make:module', ['name' => 'Orders', '--api' => true])->assertExitCode(0);
    File::ensureDirectoryExists(database_path('migrations/tenant'));
    touch(database_path('migrations/tenant/2020_01_01_000000_create_orders_table.php'));

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order'])
        ->expectsOutputToContain('"database/migrations/tenant/2020_01_01_000000_create_orders_table.php" already exists.')
        ->assertExitCode(1);
});

it('tells tenant crud users to run tenant migrations', function () {
    $this->artisan('make:module', ['name' => 'Orders', '--api' => true])->assertExitCode(0);

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order'])
        ->expectsOutputToContain('Next: php artisan tenants:migrate && php artisan test --filter=OrderApiTest')
        ->assertExitCode(0);
});

it('keeps a route-less central module central', function () {
    $this->artisan('make:module', ['name' => 'Billing', '--central' => true])->assertExitCode(0);

    expect(file_get_contents(app_path('Modules/Billing/Provider.php')))->toContain("'central'");

    $this->artisan('make:crud', ['module' => 'Billing', 'entity' => 'Invoice'])
        ->expectsOutputToContain('the way make:module --api --central does, then re-run.')
        ->assertExitCode(1);
});

it('refuses --feature on central modules', function () {
    useTestFlags();
    $this->artisan('make:module', ['name' => 'Admin', '--api' => true, '--central' => true])->assertExitCode(0);

    $this->artisan('make:crud', ['module' => 'Admin', 'entity' => 'Plan', '--feature' => 'orders'])
        ->expectsOutputToContain('Features are per tenant; central module routes have no tenant.')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Admin/Models')))->toBeFalse();
});

it('refuses every generator in tenant-saas without stancl', function (string $command, array $arguments) {
    $this->artisan('make:module', ['name' => 'Orders', '--api' => true])->assertExitCode(0);
    app()->instance(TenancyDetector::class, new class extends TenancyDetector
    {
        public function stancl(): bool
        {
            return false;
        }
    });

    $this->artisan($command, $arguments)
        ->expectsOutputToContain('Tenancy needs stancl/tenancy. Run: composer require stancl/tenancy:^3.10')
        ->assertExitCode(1);
})->with([
    ['make:crud', ['module' => 'Orders', 'entity' => 'Order']],
    ['make:dto', ['module' => 'Orders', 'name' => 'OrderData']],
    ['make:action', ['module' => 'Orders', 'name' => 'CreateOrder']],
    ['make:service', ['module' => 'Orders', 'name' => 'OrderPricing']],
]);

it('refuses modules made before tenant-saas', function () {
    config()->set('blueprint.profile', 'modular');
    $this->artisan('make:module', ['name' => 'Orders', '--api' => true])->assertExitCode(0);
    config()->set('blueprint.profile', 'tenant-saas');

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order'])
        ->expectsOutputToContain("Module \"Orders\" was made before tenant-saas: its routes use neither the 'tenant' nor the 'central' group.")
        ->assertExitCode(1);
});

it('checks the central domain in generated tenant tests', function () {
    $this->artisan('make:module', ['name' => 'Orders', '--api' => true, '--web' => true])->assertExitCode(0);
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--api' => true, '--web' => true])->assertExitCode(0);

    expect(file_get_contents(base_path('tests/Feature/Modules/Orders/OrderApiTest.php')))->toContain("it('is not served on the central domain'")->toContain("getJson(url('/api/orders'))->assertNotFound()")
        ->and(file_get_contents(base_path('tests/Feature/Modules/Orders/OrderWebTest.php')))->toContain("it('is not served on the central domain'")->toContain("get(url('/orders'))->assertNotFound()");

    File::deleteDirectory(resource_path('views/orders'));
    File::deleteDirectory(resource_path('views/layouts'));
});
