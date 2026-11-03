<?php

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    File::deleteDirectory(resource_path('views/catalog'));
    config()->set('blueprint.profile', 'modular');
    config()->set('blueprint.module_path', 'app/Modules');
    config()->set('blueprint.route_auth_web', 'auth');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    $this->artisan('make:module', ['name' => 'Catalog', '--web' => true])->assertExitCode(0);
});

afterEach(function (): void {
    File::deleteDirectory(app_path('Modules'));
    File::deleteDirectory(resource_path('views/catalog'));
    File::deleteDirectory(resource_path('views/layouts'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Catalog'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    file_put_contents($this->providersPath, $this->providers);
});

it('adds session auth to generated web routes', function (): void {
    $this->artisan('make:crud', [
        'module' => 'Catalog',
        'entity' => 'Item',
        '--web' => true,
        '--open-policy' => true,
        '--fields' => 'name:string',
    ])->assertExitCode(0);

    expect(file_get_contents(app_path('Modules/Catalog/routes/web.php')))
        ->toContain("->middleware('auth')");
});
