<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    config()->set('app.url', 'http://localhost');
    File::put(database_path('migrations/0001_01_01_000000_create_users_table.php'), "<?php\n// users\n");
});

afterEach(function () {
    File::delete([
        config_path('tenancy.php'), config_path('blueprint.php'), base_path('routes/tenant.php'),
        app_path('Models/Tenant.php'), app_path('Providers/TenancyServiceProvider.php'),
    ]);
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(app_path('Shared'));
    File::deleteDirectory(app_path('Models'));
    file_put_contents($this->providersPath, $this->providers);
});

it('creates both definitions and both migrations in tenant-saas', function () {
    File::ensureDirectoryExists(app_path('Models'));
    File::copy(__DIR__.'/../Fixtures/user-models/laravel-13.php.txt', app_path('Models/User.php'));

    $this->artisan('engineering:install', ['--profile' => 'tenant-saas', '--permissions' => true, '--no-interaction' => true])
        ->expectsOutputToContain('created app/Shared/Permissions/Permissions.php')
        ->expectsOutputToContain('created app/Shared/Permissions/CentralPermissions.php')
        ->assertExitCode(0);

    expect(glob(database_path('migrations/*_000300_create_permission_tables.php')))->toHaveCount(1)
        ->and(glob(database_path('migrations/tenant/*_000300_create_permission_tables.php')))->toHaveCount(1)
        ->and(file_get_contents(app_path('Shared/Permissions/CentralPermissions.php')))
        ->toContain('final class CentralPermissions')->toContain('central')
        ->and(file_get_contents(app_path('Shared/Permissions/Permissions.php')))->toContain('tenant')
        ->and(file_get_contents(app_path('Models/User.php')))->toContain('HasRoles');

    $this->artisan('engineering:install', ['--profile' => 'tenant-saas', '--permissions' => true, '--no-interaction' => true])
        ->doesntExpectOutputToContain('created app/Shared')
        ->assertExitCode(0);

    expect(glob(database_path('migrations/*_create_permission_tables.php')))->toHaveCount(1)
        ->and(glob(database_path('migrations/tenant/*_create_permission_tables.php')))->toHaveCount(1);
});

it('tells tenant-saas apps to migrate and sync every tenant and the central database', function () {
    $this->artisan('engineering:install', ['--profile' => 'tenant-saas', '--permissions' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Permissions: define roles in the Permissions (tenants) and CentralPermissions (central) classes, then run php artisan migrate && php artisan tenants:migrate && php artisan permissions:sync && php artisan permissions:sync --central')
        ->assertExitCode(0);
});
