<?php

use App\Models\User;
use Best\Blueprint\Tenancy\TenantSetup;
use Illuminate\Support\Facades\File;

// Module/entity names here must not be used by other test files: generated classes stay loaded for the run.
beforeEach(function () {
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    spl_autoload_register(function (string $class): void {
        $roots = ['App\\Modules\\' => app_path('Modules/'), 'Database\\Factories\\' => database_path('factories/')];

        foreach ($roots as $prefix => $dir) {
            if (str_starts_with($class, $prefix) && is_file($file = $dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php')) {
                require_once $file;
            }
        }
    });

    File::ensureDirectoryExists(database_path('migrations/tenant'));
    File::copy(__DIR__.'/../../vendor/orchestra/testbench-core/laravel/migrations/0001_01_01_000000_testbench_create_users_table.php', database_path('migrations/tenant/0001_01_01_000000_create_users_table.php'));
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::deleteDirectory(database_path('migrations/tenant'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Desk'));
    File::deleteDirectory(database_path('factories/Console'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    File::deleteDirectory(resource_path('views/desk'));
    File::deleteDirectory(resource_path('views/console'));
    File::deleteDirectory(resource_path('views/layouts'));
    file_put_contents($this->providersPath, $this->providers);
});

it('serves tenant web pages only on tenant domains', function () {
    $this->artisan('make:module', ['name' => 'Desk', '--web' => true])->assertExitCode(0);
    $this->artisan('make:crud', ['module' => 'Desk', 'entity' => 'Ticket', '--web' => true, '--open-policy' => true, '--fields' => 'subject:string'])->assertExitCode(0);
    app(TenantSetup::class)->run('a', 'a.localhost', 'tenant_a');
    app()->register('App\Modules\Desk\Provider');
    $user = tenancy()->find('a')->run(fn () => User::factory()->create());

    $this->get('http://a.localhost/tickets')->assertForbidden();
    $this->actingAs($user)->post('http://a.localhost/tickets', ['subject' => 'Printer'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('http://a.localhost/tickets/1');
    $this->actingAs($user)->get('http://a.localhost/tickets/1')->assertOk()->assertSee('Printer')->assertSee('Ticket created.');
    $this->actingAs($user)->get('http://localhost/tickets')->assertNotFound();
});

it('links central web pages with central route names', function () {
    require_once __DIR__.'/../Fixtures/User.php';
    $this->artisan('make:module', ['name' => 'Console', '--web' => true, '--central' => true])->assertExitCode(0);
    $this->artisan('make:crud', ['module' => 'Console', 'entity' => 'Plan', '--web' => true, '--open-policy' => true, '--fields' => 'title:string'])->assertExitCode(0);
    $this->artisan('migrate', ['--path' => glob(database_path('migrations/*_create_plans_table.php'))[0], '--realpath' => true])->assertExitCode(0);
    $this->loadLaravelMigrations();
    app()->register('App\Modules\Console\Provider');
    app('router')->getRoutes()->refreshNameLookups();
    $user = User::factory()->create();

    $this->actingAs($user)->get('http://localhost/plans/create')->assertOk()->assertSee('action="http://localhost/plans"', false);
    $this->actingAs($user)->post('http://localhost/plans', ['title' => 'Pro'])->assertRedirect('http://localhost/plans/1');
    $this->actingAs($user)->get('http://localhost/plans')->assertOk()->assertSee('http://localhost/plans/1');
    $this->actingAs($user)->get('http://a.localhost/plans')->assertNotFound();
    expect(route('central.plans.index'))->toBe('http://localhost/plans');
});
