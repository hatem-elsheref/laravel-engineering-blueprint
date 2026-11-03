<?php

use Best\Blueprint\Support\Compatibility;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::delete([config_path('blueprint.php'), app()->getCachedConfigPath()]);
    config()->set('blueprint.profile', null);
});

afterEach(function () {
    File::delete([config_path('blueprint.php'), app()->getCachedConfigPath()]);
});

function installedConfig(): string
{
    return file_get_contents(config_path('blueprint.php'));
}

it('installs with an explicit profile', function () {
    $this->artisan('engineering:install', ['--profile' => 'saas', '--no-interaction' => true])
        ->expectsOutputToContain('created config/blueprint.php')
        ->expectsOutputToContain('Installed profile: SaaS')
        ->expectsOutputToContain('✓ Modules (coming in Phase 3)')
        ->expectsOutputToContain('✓ Features (coming in Phase 7)')
        ->expectsOutputToContain('– Tenancy (not in this profile)')
        ->expectsOutputToContain('Next: php artisan engineering:about')
        ->assertExitCode(0);

    expect(installedConfig())->toContain("'profile' => 'saas',");
});

it('uses modular by default', function () {
    $this->artisan('engineering:install', ['--no-interaction' => true])->assertExitCode(0);

    expect(installedConfig())->toContain("'profile' => 'modular',");
});

it('falls back when default_profile invalid', function () {
    config()->set('blueprint.default_profile', 'nope');

    $this->artisan('engineering:install', ['--no-interaction' => true])->assertExitCode(0);

    expect(installedConfig())->toContain("'profile' => 'modular',");
});

it('asks interactively', function () {
    $this->artisan('engineering:install')
        ->expectsQuestion('Which project profile?', 'tenant-saas')
        ->assertExitCode(0);

    expect(installedConfig())->toContain("'profile' => 'tenant-saas',");
});

it('rejects unknown profile', function () {
    $this->artisan('engineering:install', ['--profile' => 'enterprise', '--no-interaction' => true])
        ->expectsOutputToContain('Unknown profile "enterprise". Choose one of: basic, modular, saas, tenant-saas.')
        ->assertExitCode(1);

    expect(file_exists(config_path('blueprint.php')))->toBeFalse();
});

it('rejects wrong-case profile', function () {
    $this->artisan('engineering:install', ['--profile' => 'SaaS', '--no-interaction' => true])->assertExitCode(1);

    expect(file_exists(config_path('blueprint.php')))->toBeFalse();
});

it('aborts on incompatible environment', function () {
    app()->instance(Compatibility::class, new Compatibility('99.0', '12'));

    $this->artisan('engineering:install', ['--profile' => 'saas', '--no-interaction' => true])
        ->expectsOutputToContain('✗ PHP >= 99.0')
        ->expectsOutputToContain('Install aborted: environment is not compatible.')
        ->assertExitCode(1);

    expect(file_exists(config_path('blueprint.php')))->toBeFalse();
});

it('creates no folders', function () {
    $this->artisan('engineering:install', ['--profile' => 'tenant-saas', '--no-interaction' => true])->assertExitCode(0);

    expect(is_dir(app_path('Modules')))->toBeFalse()
        ->and(is_dir(app_path('Shared')))->toBeFalse();
});
