<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Flags\FlagSetup;
use Best\Blueprint\Permissions\PermissionsDetector;
use Best\Blueprint\Permissions\PermissionSetup;
use Best\Blueprint\Support\Compatibility;
use Best\Blueprint\Support\ConfigWriter;
use Best\Blueprint\Support\Profile;
use Best\Blueprint\Support\ProfileWrite;
use Best\Blueprint\Tenancy\TenancyDetector;
use Best\Blueprint\Tenancy\TenancySetup;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    use RendersCompatibility;

    protected $signature = 'engineering:install
        {--profile= : basic, modular, saas or tenant-saas}
        {--force : Overwrite the published config file}
        {--permissions : Set up roles and permissions (needs spatie/laravel-permission)}
        {--allow-downgrade : Allow switching to a profile with fewer capabilities}';

    protected $description = 'Set up this app with a Laravel Engineering Blueprint project profile';

    private const ALL_CAPABILITIES = ['Modules', 'Features', 'Settings', 'Tenancy'];

    public function handle(Compatibility $compatibility, ConfigWriter $writer): int
    {
        $result = $compatibility->check();

        if (! $result->passed) {
            $this->renderCompatibility($result);
            $this->error('Install aborted: environment is not compatible.');

            return self::FAILURE;
        }

        $profile = $this->chooseProfile($writer);

        if ($profile === null) {
            return self::FAILURE;
        }

        if ($this->option('permissions')) {
            $detector = $this->laravel->make(PermissionsDetector::class);
            $error = $detector->spatie()
                ? $detector->unsupported()
                : '--permissions needs spatie/laravel-permission. Run: composer require spatie/laravel-permission';

            if ($error !== null) {
                $this->error($error);
                $this->line('Nothing was changed.');

                return self::FAILURE;
            }
        }

        if (! $this->allowsDowngrade($profile, $writer)) {
            return self::FAILURE;
        }

        if (! $this->writeConfig($writer, $profile)) {
            $this->error('Could not record the profile in config/blueprint.php.');
            $this->line("Re-run with --force or set 'profile' => '{$profile->value}' manually.");

            return self::FAILURE;
        }

        $tenancyReady = true;

        if ($profile->usesFeatures()) {
            foreach ($this->laravel->make(FlagSetup::class)->run($profile) as $line) {
                $this->line($line);
            }
        }

        if ($profile->usesTenancy()) {
            $setup = $this->laravel->make(TenancySetup::class);

            foreach ($setup->run() as $line) {
                $this->line($line);
            }

            $tenancyReady = $this->laravel->make(TenancyDetector::class)->stancl();

            if ($tenancyReady) {
                $this->warn($setup->note());

                if ($this->hasTenants()) {
                    $this->line('Existing tenants: php artisan tenants:migrate && php artisan features:sync');
                }
            }
        }

        if ($this->option('permissions')) {
            foreach ($this->laravel->make(PermissionSetup::class)->run($profile) as $line) {
                $this->line($line);
            }
        }

        foreach ($this->moduleWarnings($profile) as $warning) {
            $this->warn($warning);
        }

        $this->renderSummary($profile, $tenancyReady);

        return self::SUCCESS;
    }

    /**
     * Writes the profile to the app config. Returns false when it could not be recorded.
     */
    private function writeConfig(ConfigWriter $writer, Profile $profile): bool
    {
        $path = config_path('blueprint.php');
        $exists = file_exists($path);

        if (! $exists || $this->option('force')) {
            if ($exists) {
                copy($path, $path.'.bak');
                $this->line('backed up config/blueprint.php to config/blueprint.php.bak');
            }

            $writer->publish($path);

            if ($writer->setProfile($path, $profile)->status === ProfileWrite::UNRECORDABLE) {
                return false;
            }

            $this->line(($exists ? 'overwrote' : 'created').' config/blueprint.php');
        } else {
            $write = $writer->setProfile($path, $profile);

            if ($write->status === ProfileWrite::UNRECORDABLE) {
                return false;
            }

            $this->line($write->status === ProfileWrite::UNCHANGED
                ? "profile unchanged: {$profile->value}"
                : sprintf('updated profile from %s to %s', $write->old ?? 'none', $profile->value));
        }

        config()->set('blueprint.profile', $profile->value);

        if (is_file($this->laravel->getCachedConfigPath())) {
            $this->callSilently('config:clear');
            $this->line('cleared config cache');
        }

        return true;
    }

    /**
     * The saved profile: what the config file says, falling back to the (possibly cached) config.
     */
    private function savedProfile(ConfigWriter $writer): ?Profile
    {
        return Profile::tryFrom((string) $writer->recordedProfile(config_path('blueprint.php'))) ?? Profile::current();
    }

    private function allowsDowngrade(Profile $profile, ConfigWriter $writer): bool
    {
        $saved = $this->savedProfile($writer);
        $dropped = $saved === null ? [] : array_values(array_diff($saved->capabilities(), $profile->capabilities()));

        if ($dropped === [] || $this->option('force') || $this->option('allow-downgrade')) {
            return true;
        }

        $list = implode(', ', $dropped);

        if (! $this->input->isInteractive()) {
            $this->error("Refusing to drop {$list}. Re-run with --allow-downgrade.");

            return false;
        }

        if ($this->confirm("This removes {$list} from the project. Continue?", false)) {
            return true;
        }

        $this->line('Install cancelled; nothing was changed.');

        return false;
    }

    private function chooseProfile(ConfigWriter $writer): ?Profile
    {
        $option = $this->option('profile');

        if ($option !== null) {
            $profile = Profile::tryFrom($option);

            if ($profile === null) {
                $this->error(sprintf('Unknown profile "%s". Choose one of: %s.', $option, implode(', ', Profile::values())));
            }

            return $profile;
        }

        $default = $this->defaultProfile($writer);

        if (! $this->input->isInteractive()) {
            return $default;
        }

        foreach (Profile::cases() as $case) {
            $this->line("  {$case->value}: {$case->label()}");
        }

        return Profile::from($this->choice('Which project profile?', Profile::values(), $default->value));
    }

    private function defaultProfile(ConfigWriter $writer): Profile
    {
        $configured = config('blueprint.default_profile');

        return $this->savedProfile($writer)
            ?? (is_string($configured) ? Profile::tryFrom($configured) : null)
            ?? Profile::Modular;
    }

    /**
     * Existing modules that the new profile breaks or leaves half set up.
     *
     * @return list<string>
     */
    private function moduleWarnings(Profile $profile): array
    {
        $root = base_path(trim((string) config('blueprint.module_path', 'app/Modules'), '/'));
        $tenancyGroups = $plain = $gated = [];

        foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $provider = is_file($dir.'/Provider.php') ? (string) file_get_contents($dir.'/Provider.php') : '';
            $routes = implode('', array_map('file_get_contents', glob($dir.'/routes/*.php') ?: []));

            if (str_contains($provider, "'tenant'") || str_contains($provider, "'central'")) {
                $tenancyGroups[] = basename($dir);
            } elseif (str_contains($provider, 'Route::')) {
                $plain[] = basename($dir);
            }

            if (str_contains($routes, 'feature:')) {
                $gated[] = basename($dir);
            }
        }

        return array_values(array_filter([
            ! $profile->usesTenancy() && $tenancyGroups !== []
                ? "These modules use the 'tenant' or 'central' route groups, which only exist in tenant-saas: ".implode(', ', $tenancyGroups).'.'
                : null,
            ! $profile->usesFeatures() && $gated !== []
                ? 'These modules gate routes with feature middleware, which needs saas or tenant-saas: '.implode(', ', $gated).'.'
                : null,
            $profile->usesTenancy() && $plain !== []
                ? "Modules made before tenant-saas (routes use neither 'tenant' nor 'central'): ".implode(', ', $plain).". Add 'tenant' or 'central' to the Route::middleware(...) calls in their Provider.php."
                : null,
        ]));
    }

    private function hasTenants(): bool
    {
        try {
            return tenancy()->query()->exists();
        } catch (\Throwable) {
            return false; // tenants table not migrated yet
        }
    }

    private function renderSummary(Profile $profile, bool $tenancyReady = true): void
    {
        // Existing tenants and the central database need the new tables before a sync (and before any gate check).
        if ($this->option('permissions')) {
            $this->line($profile->usesTenancy()
                ? 'Permissions: define roles in the Permissions (tenants) and CentralPermissions (central) classes, then run php artisan migrate && php artisan tenants:migrate && php artisan permissions:sync && php artisan permissions:sync --central'
                : 'Permissions: define roles in the Permissions class, then run php artisan migrate && php artisan permissions:sync');
        }

        $this->newLine();
        $this->info('Installed profile: '.$profile->label());

        foreach (self::ALL_CAPABILITIES as $capability) {
            if (! in_array($capability, $profile->capabilities(), true)) {
                $this->line("– {$capability} (not in this profile)");

                continue;
            }

            if ($capability === 'Tenancy' && ! $tenancyReady) {
                $this->line('✗ Tenancy (stancl/tenancy not installed)');

                continue;
            }

            $this->line("✓ {$capability}");
        }

        if ($profile->usesSettings()) {
            $this->line('Define the manage-settings Gate ability to let tenant owners change settings.');
        }

        if ($profile->usesFeatures() && ! $profile->usesTenancy()) {
            $this->line('Next: php artisan migrate && php artisan features:sync');

            return;
        }

        if ($profile->usesTenancy()) {
            $this->line($tenancyReady
                ? 'Next: php artisan migrate && php artisan tenant:create acme'
                : 'Next: composer require stancl/tenancy:^3.10');

            return;
        }

        $this->line($profile->usesModules()
            ? 'Next: php artisan make:module <Name>'
            : 'Next: php artisan engineering:about');
    }
}
