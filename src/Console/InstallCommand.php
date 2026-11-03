<?php

namespace Best\Blueprint\Console;

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
        {--allow-downgrade : Allow switching to a profile with fewer capabilities}';

    protected $description = 'Set up this app with a Laravel Engineering Blueprint project profile';

    /**
     * Capabilities whose phase has not shipped yet. Delete an entry when its phase ships.
     */
    private const UPCOMING = ['Features' => 7, 'Settings' => 7];

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

        if (! $this->allowsDowngrade($profile, $writer)) {
            return self::FAILURE;
        }

        if (! $this->writeConfig($writer, $profile)) {
            $this->error('Could not record the profile in config/blueprint.php.');
            $this->line("Re-run with --force or set 'profile' => '{$profile->value}' manually.");

            return self::FAILURE;
        }

        $tenancyReady = true;

        if ($profile->usesTenancy()) {
            $setup = $this->laravel->make(TenancySetup::class);

            foreach ($setup->run() as $line) {
                $this->line($line);
            }

            $tenancyReady = $this->laravel->make(TenancyDetector::class)->stancl();

            if ($tenancyReady) {
                $this->warn($setup->note());
            }
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

    private function renderSummary(Profile $profile, bool $tenancyReady = true): void
    {
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

            $suffix = isset(self::UPCOMING[$capability]) ? ' (coming in Phase '.self::UPCOMING[$capability].')' : '';
            $this->line("✓ {$capability}{$suffix}");
        }

        $modulesShipped = ! isset(self::UPCOMING['Modules']);

        if ($profile->usesTenancy() && ! $tenancyReady) {
            $this->line('Next: composer require stancl/tenancy:^3.10');

            return;
        }

        if ($profile->usesTenancy() && $tenancyReady) {
            $this->line('Next: php artisan migrate && php artisan tenant:create acme');

            return;
        }

        $this->line($profile->usesModules() && $modulesShipped
            ? 'Next: php artisan make:module <Name>'
            : 'Next: php artisan engineering:about');
    }
}
