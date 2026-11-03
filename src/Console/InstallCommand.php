<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Support\Compatibility;
use Best\Blueprint\Support\ConfigWriter;
use Best\Blueprint\Support\Profile;
use Best\Blueprint\Support\ProfileWrite;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    use RendersCompatibility;

    protected $signature = 'engineering:install
        {--profile= : basic, modular, saas or tenant-saas}
        {--force : Overwrite the published config file}';

    protected $description = 'Set up this app with a Laravel Engineering Blueprint project profile';

    /**
     * Capabilities whose phase has not shipped yet. Delete an entry when its phase ships.
     */
    private const UPCOMING = ['Modules' => 3, 'Tenancy' => 6, 'Features' => 7, 'Settings' => 7];

    private const ALL_CAPABILITIES = ['Modules', 'Features', 'Settings', 'Tenancy'];

    public function handle(Compatibility $compatibility, ConfigWriter $writer): int
    {
        $result = $compatibility->check();

        if (! $result->passed) {
            $this->renderCompatibility($result);
            $this->error('Install aborted: environment is not compatible.');

            return self::FAILURE;
        }

        $profile = $this->chooseProfile();

        if ($profile === null) {
            return self::FAILURE;
        }

        if (! $this->allowsDowngrade($profile)) {
            return self::FAILURE;
        }

        if (! $this->writeConfig($writer, $profile)) {
            $this->error('Could not record the profile in config/blueprint.php.');
            $this->line("Re-run with --force or set 'profile' => '{$profile->value}' manually.");

            return self::FAILURE;
        }

        $this->renderSummary($profile);

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
            $writer->setProfile($path, $profile);
            $this->line(($exists ? 'overwrote' : 'created').' config/blueprint.php');
        } else {
            $write = $writer->setProfile($path, $profile);

            if ($write->status === ProfileWrite::UNRECORDABLE) {
                return false;
            }

            if ($write->status === ProfileWrite::UNCHANGED) {
                $this->line("profile unchanged: {$profile->value}");

                return true;
            }

            $this->line(sprintf('updated profile: %s → %s', $write->old ?? 'none', $profile->value));
        }

        config()->set('blueprint.profile', $profile->value);

        if (is_file($this->laravel->getCachedConfigPath())) {
            $this->callSilently('config:clear');
            $this->line('cleared config cache');
        }

        return true;
    }

    private function allowsDowngrade(Profile $profile): bool
    {
        $saved = Profile::current();
        $dropped = $saved === null ? [] : array_values(array_diff($saved->capabilities(), $profile->capabilities()));

        if ($dropped === [] || $this->option('force')) {
            return true;
        }

        $list = implode(', ', $dropped);

        if (! $this->input->isInteractive()) {
            $this->error("Refusing to drop {$list} without --force.");

            return false;
        }

        return $this->confirm("This removes {$list} from the project. Continue?", false);
    }

    private function chooseProfile(): ?Profile
    {
        $option = $this->option('profile');

        if ($option !== null) {
            $profile = Profile::tryFrom($option);

            if ($profile === null) {
                $this->error(sprintf('Unknown profile "%s". Choose one of: %s.', $option, implode(', ', Profile::values())));
            }

            return $profile;
        }

        $default = $this->defaultProfile();

        if (! $this->input->isInteractive()) {
            return $default;
        }

        return Profile::from($this->choice('Which project profile?', Profile::values(), $default->value));
    }

    private function defaultProfile(): Profile
    {
        return Profile::current()
            ?? Profile::tryFrom((string) config('blueprint.default_profile'))
            ?? Profile::Modular;
    }

    private function renderSummary(Profile $profile): void
    {
        $this->newLine();
        $this->info('Installed profile: '.$profile->label());

        foreach (self::ALL_CAPABILITIES as $capability) {
            if (! in_array($capability, $profile->capabilities(), true)) {
                $this->line("– {$capability} (not in this profile)");

                continue;
            }

            $suffix = isset(self::UPCOMING[$capability]) ? ' (coming in Phase '.self::UPCOMING[$capability].')' : '';
            $this->line("✓ {$capability}{$suffix}");
        }

        $modulesShipped = ! isset(self::UPCOMING['Modules']);

        $this->line($profile->usesModules() && $modulesShipped
            ? 'Next: php artisan make:module <Name>'
            : 'Next: php artisan engineering:about');
    }
}
