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
    File::deleteDirectory(base_path('stubs'));
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

const PHASE_ONE_PROVIDER_STUB = <<<'STUB'
<?php

declare(strict_types=1);

namespace App\Modules\{{ moduleNamespace }};

use Illuminate\Support\ServiceProvider;

final class {{ module }}ServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/Routes/web.php');
    }
}
STUB;

function overrideProviderStub(string $contents): void
{
    File::ensureDirectoryExists(base_path('stubs/blueprint'));
    file_put_contents(base_path('stubs/blueprint/module-provider.stub'), $contents);
}

it('refuses existing module', function (string $again) {
    $this->artisan('make:module', ['name' => 'Orders'])->assertExitCode(0);
    $providers = file_get_contents($this->providersPath);

    $this->artisan('make:module', ['name' => $again])
        ->expectsOutputToContain('Module "Orders" already exists at app/Modules/Orders.')
        ->assertExitCode(1);

    expect(file_get_contents($this->providersPath))->toBe($providers);
})->with(['Orders', 'orders']);

it('refuses old published stub', function () {
    overrideProviderStub(PHASE_ONE_PROVIDER_STUB);

    $this->artisan('make:module', ['name' => 'Orders'])
        ->expectsOutputToContain('did not produce a valid module provider (unknown placeholder {{ moduleNamespace }}). Update or delete it, then re-run.')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules')))->toBeFalse()
        ->and(file_get_contents($this->providersPath))->toBe($this->providers);
});

it('refuses stub without Provider class', function () {
    overrideProviderStub(str_replace('final class Provider', 'final class Thing', file_get_contents(__DIR__.'/../../stubs/module-provider.stub')));

    $this->artisan('make:module', ['name' => 'Orders'])
        ->expectsOutputToContain('did not produce a valid module provider (no Provider class in namespace App\Modules\Orders)')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules')))->toBeFalse();
});

it('uses a valid override stub', function () {
    overrideProviderStub(file_get_contents(__DIR__.'/../../stubs/module-provider.stub')."// custom\n");

    $this->artisan('make:module', ['name' => 'Orders'])->assertExitCode(0);

    expect(moduleFile('Orders/Provider.php'))->toContain('// custom');
});

it('warns when bootstrap providers file is missing', function () {
    unlink($this->providersPath);

    $this->artisan('make:module', ['name' => 'Orders'])
        ->expectsOutputToContain('bootstrap/providers.php not found. Register App\Modules\Orders\Provider manually.')
        ->doesntExpectOutputToContain('registered App')
        ->assertExitCode(0);

    expect(file_exists(app_path('Modules/Orders/Provider.php')))->toBeTrue();
});

it('clears caches', function () {
    file_put_contents(app()->getCachedConfigPath(), '<?php return [];');
    file_put_contents(app()->getCachedRoutesPath(), '<?php');

    $this->artisan('make:module', ['name' => 'Orders'])
        ->expectsOutputToContain('cleared config cache')
        ->expectsOutputToContain('cleared route cache')
        ->assertExitCode(0);

    expect(file_exists(app()->getCachedConfigPath()))->toBeFalse()
        ->and(file_exists(app()->getCachedRoutesPath()))->toBeFalse();
});

it('generated files are valid php', function () {
    $this->artisan('make:module', ['name' => 'Orders', '--api' => true, '--web' => true])->assertExitCode(0);

    foreach (['Orders/Provider.php', 'Orders/routes/api.php', 'Orders/routes/web.php'] as $file) {
        $lint = new Symfony\Component\Process\Process([PHP_BINARY, '-l', app_path('Modules/'.$file)]);
        $lint->run();
        expect($lint->getExitCode())->toBe(0, $lint->getOutput());
    }
});

it('generated provider loads its api routes', function () {
    $this->artisan('make:module', ['name' => 'RouteCheck', '--api' => true])->assertExitCode(0);
    file_put_contents(
        app_path('Modules/RouteCheck/routes/api.php'),
        "Route::get('/route-check', fn () => 'ok');\n",
        FILE_APPEND,
    );

    require_once app_path('Modules/RouteCheck/Provider.php');
    app()->register('App\Modules\RouteCheck\Provider');

    $this->get('/api/route-check')->assertOk()->assertSee('ok');
});

it('refuses module_path that is not a valid namespace', function (string $path) {
    config()->set('blueprint.module_path', $path);

    $this->artisan('make:module', ['name' => 'Zed'])
        ->expectsOutputToContain("module_path must be inside app/ (got \"{$path}\").")
        ->assertExitCode(1);

    expect(file_get_contents($this->providersPath))->toBe($this->providers)
        ->and(is_dir(base_path('oops')))->toBeFalse();
})->with(['app/My-Modules', 'app//Mods', 'app/../oops', 'app/Modules/2nd']);

it('refuses when a file has the module name', function () {
    File::ensureDirectoryExists(app_path('Modules'));
    touch(app_path('Modules/Foo'));

    $this->artisan('make:module', ['name' => 'Foo'])
        ->expectsOutputToContain('Module "Foo" already exists at app/Modules/Foo.')
        ->assertExitCode(1);

    expect(file_get_contents($this->providersPath))->toBe($this->providers);
});

it('refuses stub that only mentions Provider in a comment', function () {
    overrideProviderStub(str_replace('final class Provider', "// class Provider\nfinal class Thing", file_get_contents(__DIR__.'/../../stubs/module-provider.stub')));

    $this->artisan('make:module', ['name' => 'Orders'])
        ->expectsOutputToContain('did not produce a valid module provider')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules')))->toBeFalse();
});

it('rolls back a half-written module', function () {
    app()->instance(Illuminate\Filesystem\Filesystem::class, new class extends Illuminate\Filesystem\Filesystem
    {
        public function put($path, $contents, $lock = false)
        {
            if (str_ends_with($path, 'routes/web.php')) {
                throw new RuntimeException('disk full');
            }

            return parent::put($path, $contents, $lock);
        }
    });

    $this->artisan('make:module', ['name' => 'Orders', '--api' => true, '--web' => true])
        ->expectsOutputToContain('Could not write the module (disk full). Nothing was changed.')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders')))->toBeFalse()
        ->and(file_get_contents($this->providersPath))->toBe($this->providers);

    app()->forgetInstance(Illuminate\Filesystem\Filesystem::class);

    $this->artisan('make:module', ['name' => 'Orders'])->assertExitCode(0);
});
