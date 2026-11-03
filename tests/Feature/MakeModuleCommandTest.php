<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    config()->set('blueprint.profile', 'modular');
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::deleteDirectory(base_path('stubs/blueprint'));
    file_put_contents($this->providersPath, $this->providers);
    File::delete([app()->getCachedConfigPath(), app()->getCachedRoutesPath()]);
    config()->set('blueprint.stub_path', null);
});

function moduleFile(string $path): string
{
    return file_get_contents(app_path('Modules/'.$path));
}

const FULL_PROVIDER = <<<'PHP'
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

        Route::middleware('web')->group(__DIR__.'/routes/web.php');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/routes/api.php');
    }
}

PHP;

it('creates a minimal module', function () {
    $this->artisan('make:module', ['name' => 'Orders'])
        ->expectsOutputToContain('created app/Modules/Orders/Provider.php')
        ->expectsOutputToContain('registered App\Modules\Orders\Provider in bootstrap/providers.php')
        ->expectsOutputToContain('Next: add Actions, Models and Controllers as you need them. See docs/ARCHITECTURE.md.')
        ->assertExitCode(0);

    expect(moduleFile('Orders/Provider.php'))->toBe(<<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use Illuminate\Support\ServiceProvider;

final class Provider extends ServiceProvider
{
}

PHP)
        ->and(is_dir(app_path('Modules/Orders/routes')))->toBeFalse()
        ->and(file_get_contents($this->providersPath))->toContain('App\Modules\Orders\Provider::class');
});

it('creates api and web routes', function () {
    $this->artisan('make:module', ['name' => 'Orders', '--api' => true, '--web' => true])->assertExitCode(0);

    expect(moduleFile('Orders/Provider.php'))->toBe(FULL_PROVIDER);

    foreach (['api', 'web'] as $type) {
        expect(moduleFile("Orders/routes/{$type}.php"))
            ->toContain('use Illuminate\Support\Facades\Route;')
            ->toContain("// Route::get('/orders'");
    }
});

it('creates only the requested route file', function (string $flag, string $other, string $line) {
    $this->artisan('make:module', ['name' => 'Orders', "--{$flag}" => true])->assertExitCode(0);

    expect(moduleFile('Orders/Provider.php'))->toContain($line)
        ->and(substr_count(moduleFile('Orders/Provider.php'), 'Route::middleware('))->toBe(1)
        ->and(file_exists(app_path("Modules/Orders/routes/{$other}.php")))->toBeFalse();
})->with([
    'api' => ['api', 'web', "Route::middleware('api')->prefix('api')"],
    'web' => ['web', 'api', "Route::middleware('web')"],
]);

it('normalizes the name', function () {
    $this->artisan('make:module', ['name' => 'purchase-orders'])
        ->expectsOutputToContain('Using module name "PurchaseOrders".')
        ->assertExitCode(0);

    expect(file_exists(app_path('Modules/PurchaseOrders/Provider.php')))->toBeTrue();
});

it('rejects invalid names', function (string $name) {
    $this->artisan('make:module', ['name' => $name])
        ->expectsOutputToContain("Invalid module name \"{$name}\".")
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules')))->toBeFalse()
        ->and(file_get_contents($this->providersPath))->toBe($this->providers);
})->with(['123', 'Order Items!']);

it('requires a profile with modules', function (?string $profile, string $message) {
    config()->set('blueprint.profile', $profile);

    $this->artisan('make:module', ['name' => 'Orders'])
        ->expectsOutputToContain($message)
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules')))->toBeFalse();
})->with([
    'none' => [null, 'No project profile installed. Run php artisan engineering:install first.'],
    'basic' => ['basic', 'The "basic" profile has no modules. Switch profile with php artisan engineering:install --profile=modular.'],
]);

it('requires module_path inside app', function () {
    config()->set('blueprint.module_path', 'src/Modules');

    $this->artisan('make:module', ['name' => 'Orders'])
        ->expectsOutputToContain('module_path must be inside app/ (got "src/Modules").')
        ->assertExitCode(1);
});

it('accepts trailing slash in module_path', function () {
    config()->set('blueprint.module_path', 'app/Modules/');

    $this->artisan('make:module', ['name' => 'Orders'])->assertExitCode(0);

    expect(moduleFile('Orders/Provider.php'))->toContain('namespace App\Modules\Orders;');
});
