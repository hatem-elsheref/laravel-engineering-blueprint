<?php

use Best\Blueprint\Support\Compatibility;
use Best\Blueprint\Support\ConfigWriter;
use Best\Blueprint\Support\Profile;
use Best\Blueprint\Support\ProfileWrite;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;

beforeEach(function () {
    File::delete([config_path('blueprint.php'), app()->getCachedConfigPath()]);
    config()->set('blueprint.profile', null);
});

afterEach(function () {
    File::delete([config_path('blueprint.php'), config_path('blueprint.php.bak'), app()->getCachedConfigPath()]);
    File::deleteDirectory(app_path('Shared'));
    File::cleanDirectory(database_path('migrations'));
});

function installedConfig(): string
{
    return file_get_contents(config_path('blueprint.php'));
}

it('sets secure route auth defaults with --secure on a fresh config', function (): void {
    install(['--profile' => 'modular', '--secure' => true])
        ->expectsOutputToContain("Set route_auth => 'sanctum'")
        ->assertExitCode(0);

    expect(installedConfig())
        ->toContain("'route_auth' => 'sanctum'")
        ->toContain("'route_auth_web' => 'auth'");
});

it('does not overwrite route auth when --secure is passed again', function (): void {
    install(['--profile' => 'modular', '--secure' => true])->assertExitCode(0);

    install(['--profile' => 'modular', '--secure' => true])
        ->expectsOutputToContain('Secure route auth skipped')
        ->assertExitCode(0);
});

it('installs with an explicit profile', function () {
    $this->artisan('engineering:install', ['--profile' => 'saas', '--no-interaction' => true])
        ->expectsOutputToContain('created config/blueprint.php')
        ->expectsOutputToContain('Installed profile: SaaS')
        ->expectsOutputToContain('✓ Modules')
        ->doesntExpectOutputToContain('Modules (coming')
        ->expectsOutputToContain('✓ Features')
        ->expectsOutputToContain('– Tenancy (not in this profile)')
        ->expectsOutputToContain('Next: php artisan migrate && php artisan features:sync')
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
        ->expectsOutputToContain('tenant-saas: Tenant SaaS (SaaS, plus multi-tenancy with a database per tenant)')
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

    // Only folders with files in them: app/Shared holds the generated feature/setting definitions.
    expect(is_dir(app_path('Modules')))->toBeFalse()
        ->and(array_map('basename', File::allFiles(app_path('Shared'))))->toEqualCanonicalizing(['Features.php', 'Settings.php']);
});

function install(array $options = []): PendingCommand
{
    return test()->artisan('engineering:install', $options + ['--no-interaction' => true]);
}

function editConfig(string $search, string $replace): void
{
    file_put_contents(config_path('blueprint.php'), str_replace($search, $replace, installedConfig()));
}

it('rerun changes only the profile line', function () {
    install(['--profile' => 'modular'])->assertExitCode(0);
    editConfig("'app/Modules'", "'src/Modules'");

    install(['--profile' => 'saas'])
        ->expectsOutputToContain('updated profile from modular to saas')
        ->assertExitCode(0);

    expect(installedConfig())->toContain("'src/Modules'")->toContain("'profile' => 'saas',");
});

it('rerun keeps saved profile', function () {
    install(['--profile' => 'saas'])->assertExitCode(0);

    install()->expectsOutputToContain('profile unchanged: saas')->assertExitCode(0);

    expect(installedConfig())->toContain("'profile' => 'saas',");
});

it('rerun with same profile leaves file untouched', function () {
    install(['--profile' => 'saas'])->assertExitCode(0);
    editConfig("'app/Modules'", "'src/Modules'");
    $before = installedConfig();

    install(['--profile' => 'saas'])->assertExitCode(0);

    expect(installedConfig())->toBe($before);
});

it('force overwrites edits', function () {
    install(['--profile' => 'modular'])->assertExitCode(0);
    editConfig("'app/Modules'", "'src/Modules'");

    install(['--profile' => 'saas', '--force' => true])
        ->expectsOutputToContain('backed up config/blueprint.php to config/blueprint.php.bak')
        ->expectsOutputToContain('overwrote config/blueprint.php')
        ->assertExitCode(0);

    expect(file_get_contents(config_path('blueprint.php.bak')))->toContain("'src/Modules'");

    expect(installedConfig())->not->toContain("'src/Modules'")->toContain("'profile' => 'saas',");
});

it('refuses downgrade without force', function () {
    install(['--profile' => 'tenant-saas'])->assertExitCode(0);

    install(['--profile' => 'modular'])
        ->expectsOutputToContain('Refusing to drop Features, Settings, Tenancy. Re-run with --allow-downgrade.')
        ->assertExitCode(1);

    expect(installedConfig())->toContain("'profile' => 'tenant-saas',");
});

it('allows downgrade with force', function () {
    install(['--profile' => 'tenant-saas'])->assertExitCode(0);

    install(['--profile' => 'modular', '--force' => true])->assertExitCode(0);

    expect(installedConfig())->toContain("'profile' => 'modular',");
});

it('confirms downgrade interactively', function (string $answer, int $exit, string $expected) {
    install(['--profile' => 'tenant-saas'])->assertExitCode(0);

    $this->artisan('engineering:install', ['--profile' => 'modular'])
        ->expectsConfirmation('This removes Features, Settings, Tenancy from the project. Continue?', $answer)
        ->assertExitCode($exit);

    expect(installedConfig())->toContain("'profile' => '{$expected}',");
})->with([
    'declined' => ['no', 1, 'tenant-saas'],
    'accepted' => ['yes', 0, 'modular'],
]);

it('refuses unrecordable configs', function (string $search, string $replace) {
    install(['--profile' => 'modular'])->assertExitCode(0);
    editConfig($search, $replace);
    config()->set('blueprint.profile', null);
    $before = installedConfig();

    install(['--profile' => 'saas'])
        ->expectsOutputToContain('Could not record the profile in config/blueprint.php.')
        ->doesntExpectOutputToContain('Installed profile:')
        ->assertExitCode(1);

    expect(installedConfig())->toBe($before);
})->with([
    'phase-1 config' => ["    'profile' => 'modular', // set by php artisan engineering:install\n", ''],
    'env profile' => ["'profile' => 'modular',", "'profile' => env('BLUEPRINT_PROFILE', 'saas'),"],
]);

it('clears cached config', function () {
    file_put_contents(app()->getCachedConfigPath(), '<?php return [];');

    install(['--profile' => 'saas'])
        ->expectsOutputToContain('cleared config cache')
        ->assertExitCode(0);

    expect(file_exists(app()->getCachedConfigPath()))->toBeFalse();
});

it('allows downgrade without losing edits', function () {
    install(['--profile' => 'tenant-saas'])->assertExitCode(0);
    editConfig("'app/Modules'", "'src/Modules'");

    install(['--profile' => 'modular', '--allow-downgrade' => true])
        ->expectsOutputToContain('updated profile from tenant-saas to modular')
        ->assertExitCode(0);

    expect(installedConfig())->toContain("'src/Modules'")->toContain("'profile' => 'modular',");
});

it('clears cached config even when the profile is unchanged', function () {
    install(['--profile' => 'saas'])->assertExitCode(0);
    file_put_contents(app()->getCachedConfigPath(), '<?php return [];');

    install(['--profile' => 'saas'])
        ->expectsOutputToContain('profile unchanged: saas')
        ->expectsOutputToContain('cleared config cache')
        ->assertExitCode(0);

    expect(file_exists(app()->getCachedConfigPath()))->toBeFalse();
});

it('basic keeps the about hint', function () {
    install(['--profile' => 'basic'])
        ->expectsOutputToContain('Next: php artisan engineering:about')
        ->assertExitCode(0);
});

it('trusts the config file over a stale cache', function () {
    install(['--profile' => 'tenant-saas'])->assertExitCode(0);
    editConfig("'profile' => 'tenant-saas',", "'profile' => 'basic',");
    file_put_contents(app()->getCachedConfigPath(), '<?php return [];');

    install(['--profile' => 'basic'])
        ->expectsOutputToContain('profile unchanged: basic')
        ->expectsOutputToContain('cleared config cache')
        ->assertExitCode(0);
});

it('says so when a downgrade is declined', function () {
    install(['--profile' => 'tenant-saas'])->assertExitCode(0);

    $this->artisan('engineering:install', ['--profile' => 'modular'])
        ->expectsConfirmation('This removes Features, Settings, Tenancy from the project. Continue?', 'no')
        ->expectsOutputToContain('Install cancelled; nothing was changed.')
        ->assertExitCode(1);
});

it('ignores a non-string default_profile', function () {
    config()->set('blueprint.default_profile', ['saas']);

    install()->assertExitCode(0);

    expect(installedConfig())->toContain("'profile' => 'modular',");
});

it('fails when a fresh config cannot record the profile', function () {
    app()->instance(ConfigWriter::class, new class(new Filesystem) extends ConfigWriter
    {
        public function setProfile(string $path, Profile $profile): ProfileWrite
        {
            return new ProfileWrite(ProfileWrite::UNRECORDABLE);
        }
    });

    install(['--profile' => 'saas'])
        ->expectsOutputToContain('Could not record the profile in config/blueprint.php.')
        ->doesntExpectOutputToContain('Installed profile:')
        ->assertExitCode(1);
});

it('warns about modules that a profile change breaks', function () {
    File::ensureDirectoryExists(app_path('Modules/Shop'));
    file_put_contents(app_path('Modules/Shop/Provider.php'), "<?php // Route::middleware(['api', 'tenant'])\n");
    File::ensureDirectoryExists(app_path('Modules/Blog/routes'));
    file_put_contents(app_path('Modules/Blog/Provider.php'), "<?php // Route::middleware('api')\n");
    file_put_contents(app_path('Modules/Blog/routes/api.php'), "<?php // ->middleware('feature:posts')\n");
    config()->set('blueprint.profile', 'tenant-saas');

    install(['--profile' => 'modular', '--allow-downgrade' => true])
        ->expectsOutputToContain("These modules use the 'tenant' or 'central' route groups, which only exist in tenant-saas: Shop.")
        ->expectsOutputToContain('These modules gate routes with feature middleware, which needs saas or tenant-saas: Blog.')
        ->assertExitCode(0);

    File::deleteDirectory(app_path('Modules'));
});

it('lists modules that are not tenant-aware when switching to tenant-saas', function () {
    File::ensureDirectoryExists(app_path('Modules/Blog'));
    file_put_contents(app_path('Modules/Blog/Provider.php'), "<?php // Route::middleware('api')\n");

    install(['--profile' => 'tenant-saas'])
        ->expectsOutputToContain("Modules made before tenant-saas (routes use neither 'tenant' nor 'central'): Blog.")
        ->assertExitCode(0);

    File::deleteDirectory(app_path('Modules'));
});
