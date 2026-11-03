<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
});

afterEach(function () {
    File::delete([config_path('tenancy.php'), config_path('blueprint.php'), base_path('routes/tenant.php'), app_path('Models/Tenant.php'), app_path('Providers/TenancyServiceProvider.php')]);
    File::deleteDirectory(app_path('Shared'));
    File::cleanDirectory(database_path('migrations'));
    file_put_contents($this->providersPath, $this->providers);
});

it('puts feature and setting tables in tenant migrations', function () {
    $this->artisan('engineering:install', ['--profile' => 'tenant-saas', '--no-interaction' => true])->assertExitCode(0);

    expect(glob(database_path('migrations/tenant/*_create_features_table.php')))->toHaveCount(1)
        ->and(glob(database_path('migrations/tenant/*_create_settings_table.php')))->toHaveCount(1)
        ->and(glob(database_path('migrations/*_create_features_table.php')))->toBe([]);
});

it('tells existing tenants how to catch up', function () {
    app(Best\Blueprint\Tenancy\TenantSetup::class)->run('old', 'old.localhost', 'tenant_old');

    $this->artisan('engineering:install', ['--profile' => 'tenant-saas', '--no-interaction' => true])
        ->expectsOutputToContain('Existing tenants: php artisan tenants:migrate && php artisan features:sync')
        ->assertExitCode(0);
});
