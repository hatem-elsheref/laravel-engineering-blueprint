<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Support\Compatibility;
use Best\Blueprint\Support\ConfigWriter;
use Best\Blueprint\Support\Profile;
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

        $path = config_path('blueprint.php');
        $existed = file_exists($path);

        $writer->publish($path);
        $writer->setProfile($path, $profile);
        $this->line(($existed ? 'overwrote' : 'created').' config/blueprint.php');

        config()->set('blueprint.profile', $profile->value);

        $this->renderSummary($profile);

        return self::SUCCESS;
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
