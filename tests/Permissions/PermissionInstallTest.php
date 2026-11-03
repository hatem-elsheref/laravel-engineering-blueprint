<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

beforeEach(function () {
    File::ensureDirectoryExists(app_path('Models'));
    File::copy(__DIR__.'/../Fixtures/user-models/laravel-12.php.txt', app_path('Models/User.php'));
});

afterEach(function () {
    File::deleteDirectory(app_path('Shared'));
    File::deleteDirectory(app_path('Models'));
    File::cleanDirectory(database_path('migrations'));
    File::delete(config_path('blueprint.php'));
});

function installPermissions(string $profile = 'modular')
{
    return test()->artisan('engineering:install', ['--profile' => $profile, '--permissions' => true, '--no-interaction' => true]);
}

it('sets up permissions', function () {
    installPermissions()
        ->expectsOutputToContain('created app/Shared/Permissions/Permissions.php')
        ->expectsOutputToContain('created database/migrations/')
        ->expectsOutputToContain('added HasRoles to App\Models\User')
        ->expectsOutputToContain('permissions:sync')
        ->assertExitCode(0);

    $definitions = file_get_contents(app_path('Shared/Permissions/Permissions.php'));

    expect($definitions)->toContain('namespace App\Shared\Permissions;')
        ->toContain('final class Permissions')
        ->toContain('public static function groups(): array')
        ->toContain('public static function roles(): array')
        ->not->toContain('{{')
        ->and(glob(database_path('migrations/*_000300_create_permission_tables.php')))->toHaveCount(1)
        ->and(file_get_contents(app_path('Models/User.php')))->toContain('use HasFactory, Notifiable, HasRoles;');

    foreach ([app_path('Shared/Permissions/Permissions.php'), app_path('Models/User.php'), ...glob(database_path('migrations/*.php'))] as $file) {
        $lint = new Process([PHP_BINARY, '-l', $file]);
        $lint->run();
        expect($lint->getExitCode())->toBe(0, $file);
    }
});

it('is idempotent and never overwrites definitions', function () {
    installPermissions()->assertExitCode(0);
    file_put_contents(app_path('Shared/Permissions/Permissions.php'), '<?php // mine');

    installPermissions()
        ->expectsOutputToContain('already set up: app/Shared/Permissions/Permissions.php')
        ->expectsOutputToContain('already set up: permission tables migration')
        ->expectsOutputToContain('already set up: HasRoles on App\Models\User')
        ->doesntExpectOutputToContain('created ')
        ->assertExitCode(0);

    expect(file_get_contents(app_path('Shared/Permissions/Permissions.php')))->toBe('<?php // mine')
        ->and(glob(database_path('migrations/*_create_permission_tables.php')))->toHaveCount(1);
});

it('tells the user what to do when the User model cannot be edited', function () {
    file_put_contents(app_path('Models/User.php'), "<?php\n\nnamespace App\\Models;\n\nclass User\n{\n}\n");

    installPermissions()
        ->expectsOutputToContain('Add "use HasRoles;" to App\Models\User')
        ->assertExitCode(0);
});

it('tells the user when there is no User model', function () {
    File::delete(app_path('Models/User.php'));

    installPermissions()
        ->expectsOutputToContain('Add "use HasRoles;" to App\Models\User')
        ->assertExitCode(0);
});

it('refuses an unsupported spatie setup before changing anything', function (Closure $setup, string $message) {
    $setup();

    installPermissions()
        ->expectsOutputToContain($message)
        ->expectsOutputToContain('Nothing was changed.')
        ->assertExitCode(1);

    expect(file_exists(app_path('Shared/Permissions/Permissions.php')))->toBeFalse()
        ->and(file_exists(config_path('blueprint.php')))->toBeFalse();
})->with('unsupported spatie');

it('accepts spatie 8', function () {
    expect(app(\Best\Blueprint\Permissions\PermissionsDetector::class)->unsupported())->toBeNull();
});
