<?php

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    config()->set('blueprint.profile', 'modular');
    config()->set('blueprint.module_path', 'app/Modules');
    config()->set('blueprint.route_auth', 'sanctum');
    config()->set('blueprint.api_envelope', true);
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    makePermissionDefinitions();
});

afterEach(function (): void {
    File::deleteDirectory(app_path('Modules'));
    File::deleteDirectory(app_path('Shared'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Shop'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    file_put_contents($this->providersPath, $this->providers);
});

it('generates modular sanctum permissions owned api envelope for the stub matrix', function (): void {
    $before = crudOutputFiles();

    $this->artisan('make:module', ['name' => 'Shop', '--api' => true])->assertExitCode(0);
    $this->artisan('make:crud', [
        'module' => 'Shop',
        'entity' => 'Widget',
        '--api' => true,
        '--permissions' => true,
        '--owned' => true,
        '--fields' => 'title:string',
    ])->assertExitCode(0);

    $controller = file_get_contents(app_path('Modules/Shop/Controllers/WidgetController.php'));
    $apiTest = file_get_contents(base_path('tests/Feature/Modules/Shop/WidgetApiTest.php'));
    expect($controller)->toContain('ApiResponse::')
        ->and($apiTest)->toContain('ActsAsApiUser');

    assertCrudSnapshot('modular-api-envelope', $before);
});
