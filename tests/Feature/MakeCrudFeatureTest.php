<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

beforeEach(function () {
    config()->set('blueprint.profile', 'saas');
    config()->set('blueprint.module_path', 'app/Modules');
    useTestFlags();
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    $this->artisan('make:module', ['name' => 'Orders', '--api' => true])->assertExitCode(0);
    $this->routes = file_get_contents(app_path('Modules/Orders/routes/api.php'));
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Orders'));
    File::deleteDirectory(database_path('factories/Shop'));
    File::deleteDirectory(resource_path('views/shop'));
    File::deleteDirectory(resource_path('views/layouts'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    File::deleteDirectory(base_path('stubs'));
    file_put_contents($this->providersPath, $this->providers);
});

it('protects the routes with the feature', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--feature' => 'orders'])->assertExitCode(0);

    expect(file_get_contents(app_path('Modules/Orders/routes/api.php')))
        ->toContain("Route::apiResource('orders', OrderController::class)->names('api.orders')\n    ->middleware('feature:orders')\n    ->missing(");

    $test = file_get_contents(base_path('tests/Feature/Modules/Orders/OrderApiTest.php'));

    expect($test)->toContain("app(FeatureAdmin::class)->enable('orders');")
        ->toContain("it('is blocked when the orders feature is disabled'")
        ->toContain('use Best\Blueprint\Features\FeatureAdmin;');

    $lint = new Process([PHP_BINARY, '-l', base_path('tests/Feature/Modules/Orders/OrderApiTest.php')]);
    $lint->run();
    expect($lint->getExitCode())->toBe(0);
});

it('leaves routes alone without --feature', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order'])->assertExitCode(0);

    expect(file_get_contents(app_path('Modules/Orders/routes/api.php')))->not->toContain('feature:')
        ->and(file_get_contents(base_path('tests/Feature/Modules/Orders/OrderApiTest.php')))->not->toContain('FeatureAdmin');
});

it('refuses unknown features', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--feature' => 'nope'])
        ->expectsOutputToContain('Unknown feature "nope". Add it to Best\Blueprint\Tests\Fixtures\TestFeatures first.')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Models')))->toBeFalse();
});

it('refuses --feature without features', function () {
    config()->set('blueprint.profile', 'modular');

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--feature' => 'orders'])
        ->expectsOutputToContain('--feature needs the saas or tenant-saas profile.')
        ->assertExitCode(1);
});

it('refuses an old route stub that would drop the feature gate', function () {
    File::ensureDirectoryExists(base_path('stubs/blueprint'));
    file_put_contents(base_path('stubs/blueprint/route.stub'), "Route::apiResource('{{ uri }}', {{ controller }}::class)->names('api.{{ uri }}');\n");

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--feature' => 'orders'])
        ->expectsOutputToContain('did not produce a valid route (no feature:orders middleware)')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Models')))->toBeFalse()
        ->and(file_get_contents(app_path('Modules/Orders/routes/api.php')))->toBe($this->routes);
});

it('refuses an old test stub that would drop the feature setup', function () {
    File::ensureDirectoryExists(base_path('stubs/blueprint'));
    $old = str_replace(['{{ featureSetup }}', '{{ featureTest }}'], '', file_get_contents(__DIR__.'/../../stubs/test-deny.stub'));
    file_put_contents(base_path('stubs/blueprint/test-deny.stub'), $old);

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--feature' => 'orders'])
        ->expectsOutputToContain('did not produce valid tests (no feature setup for orders)')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Models')))->toBeFalse();
});

it('keeps the feature gate when adding a side', function () {
    $this->artisan('make:module', ['name' => 'Shop', '--api' => true, '--web' => true])->assertExitCode(0);
    $this->artisan('make:crud', ['module' => 'Shop', 'entity' => 'Coupon', '--feature' => 'reports'])->assertExitCode(0);

    $this->artisan('make:crud', ['module' => 'Shop', 'entity' => 'Coupon', '--web' => true])
        ->expectsOutputToContain('The existing routes are gated by feature:reports; add --feature=reports so the new side is gated too.')
        ->assertExitCode(1);

    $this->artisan('make:crud', ['module' => 'Shop', 'entity' => 'Coupon', '--web' => true, '--feature' => 'reports'])->assertExitCode(0);

});
