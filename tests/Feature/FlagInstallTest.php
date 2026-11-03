<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::deleteDirectory(app_path('Shared'));
    File::cleanDirectory(database_path('migrations'));
    File::delete(config_path('blueprint.php'));
});

it('sets up features and settings for saas', function () {
    $this->artisan('engineering:install', ['--profile' => 'saas', '--no-interaction' => true])
        ->expectsOutputToContain('✓ created app/Shared/Features/Features.php')
        ->expectsOutputToContain('✓ created app/Shared/Settings/Settings.php')
        ->expectsOutputToContain('✓ created database/migrations/')
        ->expectsOutputToContain('✓ Features')
        ->expectsOutputToContain('✓ Settings')
        ->doesntExpectOutputToContain('coming in Phase 7')
        ->expectsOutputToContain('Define the manage-settings Gate ability to let tenant owners change settings.')
        ->expectsOutputToContain('Next: php artisan migrate && php artisan features:sync')
        ->assertExitCode(0);

    expect(file_get_contents(app_path('Shared/Features/Features.php')))
        ->toContain('namespace App\Shared\Features;')->toContain('public static function defaults(): array')
        ->and(file_get_contents(app_path('Shared/Settings/Settings.php')))
        ->toContain('namespace App\Shared\Settings;')->toContain("'ability' => 'manage-settings'")
        ->and(glob(database_path('migrations/*_create_features_table.php')))->toHaveCount(1)
        ->and(glob(database_path('migrations/*_create_settings_table.php')))->toHaveCount(1);

    foreach ([app_path('Shared/Features/Features.php'), app_path('Shared/Settings/Settings.php'), ...glob(database_path('migrations/*.php'))] as $file) {
        $lint = new Symfony\Component\Process\Process([PHP_BINARY, '-l', $file]);
        $lint->run();
        expect($lint->getExitCode())->toBe(0, $file);
    }
});

it('is idempotent', function () {
    $this->artisan('engineering:install', ['--profile' => 'saas', '--no-interaction' => true])->assertExitCode(0);

    $this->artisan('engineering:install', ['--profile' => 'saas', '--no-interaction' => true])
        ->expectsOutputToContain('already set up: app/Shared/Features/Features.php')
        ->expectsOutputToContain('already set up: features migration')
        ->assertExitCode(0);

    expect(glob(database_path('migrations/*_create_features_table.php')))->toHaveCount(1);
});

it('does nothing for profiles without features', function () {
    $this->artisan('engineering:install', ['--profile' => 'modular', '--no-interaction' => true])->assertExitCode(0);

    expect(is_dir(app_path('Shared')))->toBeFalse()
        ->and(glob(database_path('migrations/*_create_features_table.php')))->toBe([]);
});
