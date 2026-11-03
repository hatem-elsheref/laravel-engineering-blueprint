<?php

use App\Models\User;
use App\Modules\Garage\Models\Repair;
use Best\Blueprint\Testing\InteractsWithPermissions;
use Illuminate\Support\Facades\File;

uses(InteractsWithPermissions::class);

const REPAIR_PERMISSIONS = ['repairs.view', 'repairs.create', 'repairs.update', 'repairs.delete'];

// Module/entity names here must not be used by other test files: generated classes stay loaded for the run.
beforeEach(function () {
    require_once __DIR__.'/../Fixtures/User.php';
    require_once __DIR__.'/../Fixtures/UserFactory.php';
    spl_autoload_register(function (string $class): void {
        $roots = ['App\\Modules\\' => app_path('Modules/'), 'Database\\Factories\\Garage\\' => database_path('factories/Garage/')];

        foreach ($roots as $prefix => $dir) {
            if (str_starts_with($class, $prefix) && is_file($file = $dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php')) {
                require_once $file;
            }
        }
    });

    config()->set('blueprint.profile', 'saas');
    config()->set('auth.providers.users.model', User::class);
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    makePermissionDefinitions();

    $this->artisan('make:module', ['name' => 'Garage', '--api' => true, '--web' => true])->assertExitCode(0);
    $this->artisan('make:crud', [
        'module' => 'Garage', 'entity' => 'Repair', '--api' => true, '--web' => true, '--permissions' => true, '--owned' => true, '--fields' => 'name:string',
    ])->assertExitCode(0);

    $this->loadLaravelMigrations();
    $this->artisan('migrate', ['--path' => glob(database_path('migrations/*_create_repairs_table.php'))[0], '--realpath' => true])->assertExitCode(0);
    app()->register('App\Modules\Garage\Provider');
    app('router')->getRoutes()->refreshNameLookups();
    $this->user = User::factory()->create();
    $this->other = User::factory()->create();
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::deleteDirectory(app_path('Shared'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Garage'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    File::deleteDirectory(resource_path('views/garage'));
    File::deleteDirectory(resource_path('views/layouts'));
    file_put_contents($this->providersPath, $this->providers);
});

it('lets the owner do everything and sets them as owner', function () {
    $this->grantPermissions($this->user, ...REPAIR_PERMISSIONS);
    $this->actingAs($this->user);

    $id = $this->postJson('/api/repairs', ['name' => 'Garage', 'user_id' => $this->other->id])->assertCreated()->json('data.id');
    expect(Repair::find($id)->user_id)->toBe($this->user->id);

    $this->getJson('/api/repairs')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/repairs/{$id}")->assertOk();
    $this->putJson("/api/repairs/{$id}", ['name' => 'Chair', 'user_id' => $this->other->id])->assertOk();
    expect(Repair::find($id)->user_id)->toBe($this->user->id);
    $this->get('/repairs')->assertOk()->assertSee("/repairs/{$id}");
    $this->get("/repairs/{$id}/edit")->assertOk();
    $this->deleteJson("/api/repairs/{$id}")->assertNoContent();
});

it("hides other users' records as not found", function () {
    $repair = Repair::factory()->for($this->other)->create();
    $this->grantPermissions($this->user, ...REPAIR_PERMISSIONS);
    $this->actingAs($this->user);

    $this->getJson("/api/repairs/{$repair->id}")->assertNotFound();
    $this->putJson("/api/repairs/{$repair->id}", ['name' => 'Chair'])->assertNotFound();
    $this->deleteJson("/api/repairs/{$repair->id}")->assertNotFound();
    $this->getJson('/api/repairs')->assertOk()->assertJsonCount(0, 'data');
    $this->get("/repairs/{$repair->id}")->assertNotFound();
    $this->get("/repairs/{$repair->id}/edit")->assertNotFound();
    $this->put("/repairs/{$repair->id}", ['name' => 'Chair'])->assertNotFound();
    $this->delete("/repairs/{$repair->id}")->assertNotFound();
    $this->get('/repairs')->assertOk()->assertDontSee("/repairs/{$repair->id}");

    expect($repair->fresh()->name)->not->toBe('Chair');
});

it('denies the owner without the permission (403, not 404)', function () {
    $repair = Repair::factory()->for($this->user)->create();
    $this->grantPermissions($this->user, 'repairs.view');
    $this->actingAs($this->user);

    $this->getJson("/api/repairs/{$repair->id}")->assertOk();
    $this->putJson("/api/repairs/{$repair->id}", ['name' => 'Chair'])->assertForbidden();
    $this->deleteJson("/api/repairs/{$repair->id}")->assertForbidden();

    $this->grantPermissions($this->user, 'repairs.update');
    $this->getJson("/api/repairs/{$repair->id}")->assertForbidden();
    $this->getJson('/api/repairs')->assertForbidden();
});

it('lets an any-holder act on every record and keeps the owner', function () {
    $repair = Repair::factory()->for($this->other)->create();
    $this->grantPermissions($this->user, ...[...REPAIR_PERMISSIONS, 'repairs.any']);
    $this->actingAs($this->user);

    $this->getJson('/api/repairs')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/repairs/{$repair->id}")->assertOk();
    $this->putJson("/api/repairs/{$repair->id}", ['name' => 'Chair', 'user_id' => $this->user->id])->assertOk();

    expect($repair->fresh()->user_id)->toBe($this->other->id)
        ->and($repair->fresh()->name)->toBe('Chair');

    $this->deleteJson("/api/repairs/{$repair->id}")->assertNoContent();
});

it('widens only the permissions held', function () {
    $repair = Repair::factory()->for($this->other)->create();
    $this->grantPermissions($this->user, 'repairs.view', 'repairs.any');
    $this->actingAs($this->user);

    $this->getJson("/api/repairs/{$repair->id}")->assertOk();
    $this->deleteJson("/api/repairs/{$repair->id}")->assertForbidden();
    $this->putJson("/api/repairs/{$repair->id}", ['name' => 'Chair'])->assertForbidden();
});

it('grants nothing with any alone', function () {
    $repair = Repair::factory()->for($this->other)->create();
    $this->grantPermissions($this->user, 'repairs.any');
    $this->actingAs($this->user);

    $this->getJson('/api/repairs')->assertForbidden();
    $this->getJson("/api/repairs/{$repair->id}")->assertForbidden();
    $this->deleteJson("/api/repairs/{$repair->id}")->assertForbidden();
});

it('lets a super-admin see and change every record', function () {
    $repair = Repair::factory()->for($this->other)->create();
    $this->makeSuperAdmin($this->user);
    $this->actingAs($this->user);

    $this->getJson('/api/repairs')->assertOk()->assertJsonCount(1, 'data');
    $this->get('/repairs')->assertOk()->assertSee("/repairs/{$repair->id}");
    $this->getJson("/api/repairs/{$repair->id}")->assertOk();
    $this->putJson("/api/repairs/{$repair->id}", ['name' => 'Chair'])->assertOk();
    $this->deleteJson("/api/repairs/{$repair->id}")->assertNoContent();
});

it('denies guests', function () {
    $repair = Repair::factory()->create();

    $this->getJson('/api/repairs')->assertForbidden();
    $this->getJson("/api/repairs/{$repair->id}")->assertForbidden();
    $this->putJson("/api/repairs/{$repair->id}", ['name' => 'Chair'])->assertForbidden();
    $this->deleteJson("/api/repairs/{$repair->id}")->assertForbidden();
});
