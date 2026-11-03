<?php

use Illuminate\Support\Facades\File;

// make:crud without --permissions must keep producing exactly this output (tests/Fixtures/crud-snapshots).
beforeEach(function () {
    config()->set('blueprint.profile', 'saas');
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Shop'));
    File::deleteDirectory(resource_path('views/shop'));
    File::deleteDirectory(resource_path('views/layouts'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    file_put_contents($this->providersPath, $this->providers);
});

it('generates the same api and web crud as before', function () {
    $before = crudOutputFiles();

    $this->artisan('make:module', ['name' => 'Shop', '--api' => true, '--web' => true])->assertExitCode(0);
    $this->artisan('make:crud', [
        'module' => 'Shop',
        'entity' => 'PurchaseOrder',
        '--fields' => 'name:string,quantity:int,price:?float,active:bool,tags:array,shippedAt:?Carbon\CarbonImmutable',
        '--api' => true,
        '--web' => true,
    ])->assertExitCode(0);

    assertCrudSnapshot('saas', $before);
});
