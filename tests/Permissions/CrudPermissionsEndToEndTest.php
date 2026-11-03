<?php

use App\Models\User;
use App\Modules\Ledger\Models\Voucher;
use Best\Blueprint\Testing\InteractsWithPermissions;
use Illuminate\Support\Facades\File;

uses(InteractsWithPermissions::class);

// Module/entity names here must not be used by other test files: generated classes stay loaded for the run.
beforeEach(function () {
    require_once __DIR__.'/../Fixtures/User.php';
    require_once __DIR__.'/../Fixtures/UserFactory.php';
    spl_autoload_register(function (string $class): void {
        $roots = ['App\\Modules\\' => app_path('Modules/'), 'Database\\Factories\\Ledger\\' => database_path('factories/Ledger/')];

        foreach ($roots as $prefix => $dir) {
            if (str_starts_with($class, $prefix) && is_file($file = $dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php')) {
                require_once $file;
            }
        }
    });

    config()->set('blueprint.profile', 'saas');
    config()->set('auth.providers.users.model', User::class); // as in a real app: spatie finds the User's guard from it
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    makePermissionDefinitions();

    $this->artisan('make:module', ['name' => 'Ledger', '--api' => true, '--web' => true])->assertExitCode(0);
    $this->artisan('make:crud', [
        'module' => 'Ledger', 'entity' => 'Voucher', '--api' => true, '--web' => true, '--permissions' => true, '--fields' => 'name:string',
    ])->assertExitCode(0);

    $this->loadLaravelMigrations();
    $this->artisan('migrate', ['--path' => glob(database_path('migrations/*_create_vouchers_table.php'))[0], '--realpath' => true])->assertExitCode(0);
    app()->register('App\Modules\Ledger\Provider');
    app('router')->getRoutes()->refreshNameLookups();
    $this->user = User::factory()->create();
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::deleteDirectory(app_path('Shared'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Ledger'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    File::deleteDirectory(resource_path('views/ledger'));
    File::deleteDirectory(resource_path('views/layouts'));
    file_put_contents($this->providersPath, $this->providers);
});

it('lets a user with the permissions do everything', function () {
    $this->grantPermissions($this->user, 'vouchers.view', 'vouchers.create', 'vouchers.update', 'vouchers.delete');
    $this->actingAs($this->user);

    $id = $this->postJson('/api/vouchers', ['name' => 'Desk'])->assertCreated()->json('data.id');
    $this->getJson('/api/vouchers')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/vouchers/{$id}")->assertOk();
    $this->putJson("/api/vouchers/{$id}", ['name' => 'Chair'])->assertOk();
    $this->get('/vouchers')->assertOk();
    $this->get("/vouchers/{$id}/edit")->assertOk();
    $this->deleteJson("/api/vouchers/{$id}")->assertNoContent();
});

it('checks each ability on its own', function () {
    $id = Voucher::factory()->create()->id;
    $this->grantPermissions($this->user, 'vouchers.view');
    $this->actingAs($this->user);

    $this->getJson('/api/vouchers')->assertOk();
    $this->getJson("/api/vouchers/{$id}")->assertOk();
    $this->postJson('/api/vouchers', ['name' => 'Desk'])->assertForbidden();
    $this->putJson("/api/vouchers/{$id}", ['name' => 'Chair'])->assertForbidden();
    $this->deleteJson("/api/vouchers/{$id}")->assertForbidden();
    $this->get('/vouchers/create')->assertForbidden();
});

it('denies a user without the permissions', function () {
    $this->grantPermissions(User::factory()->create(), 'vouchers.view', 'vouchers.create', 'vouchers.update', 'vouchers.delete');
    $id = Voucher::factory()->create()->id;
    $this->actingAs($this->user);

    $this->getJson('/api/vouchers')->assertForbidden();
    $this->postJson('/api/vouchers', ['name' => 'Desk'])->assertForbidden();
    $this->getJson("/api/vouchers/{$id}")->assertForbidden();
    $this->putJson("/api/vouchers/{$id}", ['name' => 'Chair'])->assertForbidden();
    $this->deleteJson("/api/vouchers/{$id}")->assertForbidden();
    $this->get('/vouchers')->assertForbidden();
});

it('denies instead of erroring when permissions were never synced', function () {
    $id = Voucher::factory()->create()->id;
    $this->actingAs($this->user);

    $this->getJson('/api/vouchers')->assertForbidden();
    $this->getJson("/api/vouchers/{$id}")->assertForbidden();
    $this->deleteJson("/api/vouchers/{$id}")->assertForbidden();
    $this->get('/vouchers')->assertForbidden();
});

it('lets a super-admin in', function () {
    $this->makeSuperAdmin($this->user);
    $this->actingAs($this->user);

    $id = $this->postJson('/api/vouchers', ['name' => 'Desk'])->assertCreated()->json('data.id');
    $this->getJson('/api/vouchers')->assertOk();
    $this->deleteJson("/api/vouchers/{$id}")->assertNoContent();
    $this->get('/vouchers')->assertOk();
});
