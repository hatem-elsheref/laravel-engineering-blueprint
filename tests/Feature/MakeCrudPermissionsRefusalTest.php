<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    config()->set('blueprint.profile', 'modular');
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    $this->artisan('make:module', ['name' => 'Orders', '--api' => true])->assertExitCode(0);
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    file_put_contents($this->providersPath, $this->providers);
});

it('refuses --permissions without spatie and writes nothing', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--permissions' => true])
        ->expectsOutputToContain('--permissions needs spatie/laravel-permission. Run: composer require spatie/laravel-permission && php artisan engineering:install --permissions')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Models')))->toBeFalse();
});
