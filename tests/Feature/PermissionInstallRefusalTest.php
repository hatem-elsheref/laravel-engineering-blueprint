<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::deleteDirectory(app_path('Shared'));
    File::cleanDirectory(database_path('migrations'));
    File::delete(config_path('blueprint.php'));
});

it('refuses --permissions without spatie and writes nothing', function () {
    $this->artisan('engineering:install', ['--profile' => 'modular', '--permissions' => true, '--no-interaction' => true])
        ->expectsOutputToContain('composer require spatie/laravel-permission')
        ->assertExitCode(1);

    expect(file_exists(config_path('blueprint.php')))->toBeFalse()
        ->and(is_dir(app_path('Shared')))->toBeFalse()
        ->and(glob(database_path('migrations/*')))->toBe([]);
});
