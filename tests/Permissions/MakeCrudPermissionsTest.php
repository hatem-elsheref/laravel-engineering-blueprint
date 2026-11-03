<?php

use Best\Blueprint\Permissions\PermissionsDetector;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

const ORDER_ENTRY = "'orders' => ['view', 'create', 'update', 'delete'],";

beforeEach(function () {
    config()->set('blueprint.profile', 'saas');
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    makePermissionDefinitions();
    $this->definitions = app_path('Shared/Permissions/Permissions.php');
    $this->artisan('make:module', ['name' => 'Orders', '--api' => true, '--web' => true])->assertExitCode(0);
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::deleteDirectory(app_path('Shared'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Orders'));
    File::deleteDirectory(resource_path('views/orders'));
    File::deleteDirectory(resource_path('views/layouts'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    File::deleteDirectory(base_path('stubs'));
    file_put_contents($this->providersPath, $this->providers);
});

function lints(string $path): void
{
    $lint = new Process([PHP_BINARY, '-l', $path]);
    $lint->run();
    expect($lint->getExitCode())->toBe(0, $path.': '.$lint->getOutput());
}

it('generates a policy that checks named permissions', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--permissions' => true])->assertExitCode(0);

    $policy = file_get_contents(app_path('Modules/Orders/Policies/OrderPolicy.php'));

    expect($policy)->toContain('final class OrderPolicy')
        ->toContain("private const GROUP = 'orders';")
        ->toContain("return \$user->checkPermissionTo(self::GROUP.'.view');")
        ->toContain("return \$user->checkPermissionTo(self::GROUP.'.create');")
        ->toContain("return \$user->checkPermissionTo(self::GROUP.'.update');")
        ->toContain("return \$user->checkPermissionTo(self::GROUP.'.delete');")
        ->not->toContain("'orders.")
        ->and(substr_count($policy, 'checkPermissionTo('))->toBe(5);

    lints(app_path('Modules/Orders/Policies/OrderPolicy.php'));
});

it('adds the group to the definitions file once', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--permissions' => true])
        ->expectsOutputToContain("added 'orders' to app/Shared/Permissions/Permissions.php")
        ->assertExitCode(0);
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Invoice', '--permissions' => true])->assertExitCode(0);
    $afterTwo = file_get_contents($this->definitions);

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--web' => true, '--permissions' => true])
        ->doesntExpectOutputToContain("added 'orders'")
        ->assertExitCode(0);

    expect(substr_count($afterTwo, ORDER_ENTRY))->toBe(1)
        ->and($afterTwo)->toContain(ORDER_ENTRY."\n            'invoices' => ['view', 'create', 'update', 'delete'],\n        ];")
        ->and(file_get_contents($this->definitions))->toBe($afterTwo);

    lints($this->definitions);
});

it('writes the permission tests', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--api' => true, '--web' => true, '--permissions' => true])->assertExitCode(0);

    foreach (['OrderApiTest', 'OrderWebTest'] as $name) {
        $path = base_path("tests/Feature/Modules/Orders/{$name}.php");
        $test = file_get_contents($path);

        expect($test)->toContain("    \$this->grantPermissions(\$this->user, 'orders.view', 'orders.create', 'orders.update', 'orders.delete');\n})")
            ->toContain('use Best\Blueprint\Testing\InteractsWithPermissions;')
            ->toContain('uses(RefreshDatabase::class, InteractsWithPermissions::class);')
            ->toContain("it('denies users without the permission'")
            ->toContain("it('lets a super-admin in'")
            ->toContain('$this->makeSuperAdmin($user);');

        lints($path);
    }
});

it('prints the next step', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--permissions' => true])
        ->expectsOutputToContain("Next: add 'orders.*' (or single permissions) to a role in app/Shared/Permissions/Permissions.php, then php artisan permissions:sync")
        ->doesntExpectOutputToContain('lets every signed-in user do everything')
        ->assertExitCode(0);
});

it('reminds about --permissions when permissions are set up', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order'])
        ->expectsOutputToContain('Tip: --permissions makes the policy check named permissions.')
        ->expectsOutputToContain('lets every signed-in user do everything')
        ->assertExitCode(0);

    expect(file_get_contents(app_path('Modules/Orders/Policies/OrderPolicy.php')))->not->toContain('checkPermissionTo(')
        ->and(file_get_contents($this->definitions))->not->toContain("'orders'");
});

it('refuses when spatie is not loaded', function () {
    app()->instance(PermissionsDetector::class, new class extends PermissionsDetector
    {
        public function spatie(): bool
        {
            return false;
        }
    });
    $definitions = file_get_contents($this->definitions);

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--permissions' => true])
        ->expectsOutputToContain('--permissions needs spatie/laravel-permission. Run: composer require spatie/laravel-permission && php artisan engineering:install --permissions')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Models')))->toBeFalse()
        ->and(file_get_contents($this->definitions))->toBe($definitions);
});

it('refuses when the definitions class is missing', function () {
    config()->set('blueprint.permissions', 'App\Shared\Permissions\Missing');

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--permissions' => true])
        ->expectsOutputToContain('Permission definitions not found (App\Shared\Permissions\Missing). Run: php artisan engineering:install --permissions')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Models')))->toBeFalse();
});

it('refuses a definitions file it cannot edit and prints the line to add', function () {
    file_put_contents($this->definitions, str_replace("return [\n            // 'products' => ['view', 'create', 'update', 'delete'],\n        ];", 'return self::GROUPS;', file_get_contents($this->definitions)));
    $definitions = file_get_contents($this->definitions);

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--permissions' => true])
        ->expectsOutputToContain('Could not find the array returned by groups() in app/Shared/Permissions/Permissions.php. Add '.ORDER_ENTRY.' to it, then re-run.')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Models')))->toBeFalse()
        ->and(file_get_contents($this->definitions))->toBe($definitions);
});

it('accepts a group added by hand', function () {
    file_put_contents($this->definitions, str_replace("// 'products' =>", ORDER_ENTRY."\n            // 'products' =>", file_get_contents($this->definitions)));
    $definitions = file_get_contents($this->definitions);

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--permissions' => true])->assertExitCode(0);

    expect(file_get_contents($this->definitions))->toBe($definitions);
});

it('keeps the permissions mode when adding a side', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--permissions' => true])->assertExitCode(0);
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--web' => true])
        ->expectsOutputToContain('The existing Order CRUD uses permissions; add --permissions.')
        ->assertExitCode(1);

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Coupon'])->assertExitCode(0);
    $definitions = file_get_contents($this->definitions);
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Coupon', '--web' => true, '--permissions' => true])
        ->expectsOutputToContain('The existing Coupon CRUD does not use permissions; leave --permissions out.')
        ->assertExitCode(1);

    expect(file_exists(app_path('Modules/Orders/Controllers/OrderPageController.php')))->toBeFalse()
        ->and(file_exists(app_path('Modules/Orders/Controllers/CouponPageController.php')))->toBeFalse()
        ->and(file_get_contents($this->definitions))->toBe($definitions);

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--web' => true, '--permissions' => true])->assertExitCode(0);
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Coupon', '--web' => true])->assertExitCode(0);
});

it('refuses a published policy stub that would drop the permission checks', function () {
    File::ensureDirectoryExists(base_path('stubs/blueprint'));
    file_put_contents(base_path('stubs/blueprint/policy-permissions.stub'), file_get_contents(__DIR__.'/../../stubs/policy.stub'));
    $definitions = file_get_contents($this->definitions);

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--permissions' => true])
        ->expectsOutputToContain('policy-permissions.stub did not produce a valid policy (no checkPermissionTo). Update or delete it, then re-run.')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Models')))->toBeFalse()
        ->and(file_get_contents($this->definitions))->toBe($definitions);
});

it('refuses an old test stub that would drop the permission setup', function (string $stub, array $sides) {
    File::ensureDirectoryExists(base_path('stubs/blueprint'));
    $old = str_replace(['{{ permissionSetup }}', '{{ permissionTest }}', '{{ permissionUses }}'], '', file_get_contents(__DIR__."/../../stubs/{$stub}"));
    file_put_contents(base_path("stubs/blueprint/{$stub}"), $old);

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--permissions' => true] + $sides)
        ->expectsOutputToContain("{$stub} did not produce valid tests (no permission setup). Update or delete it, then re-run.")
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Models')))->toBeFalse();
})->with([
    'api' => ['test.stub', ['--api' => true]],
    'web' => ['test-web.stub', ['--web' => true]],
]);
