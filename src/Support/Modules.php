<?php

namespace Best\Blueprint\Support;

use Best\Blueprint\Tenancy\TenancyDetector;

/**
 * Project-level checks shared by the module generators.
 */
final class Modules
{
    public function profileError(): ?string
    {
        $profile = Profile::current();

        if ($profile === null) {
            return 'No project profile installed. Run php artisan engineering:install first.';
        }

        if (! $profile->usesModules()) {
            return "The \"{$profile->value}\" profile has no modules. Switch profile with php artisan engineering:install --profile=modular.";
        }

        if ($profile->usesTenancy() && ! app(TenancyDetector::class)->stancl()) {
            return 'Tenancy needs stancl/tenancy. Run: composer require stancl/tenancy:^3.10, then php artisan engineering:install --profile=tenant-saas.';
        }

        return null;
    }

    /**
     * module_path must be "app/" followed by segments that are valid PHP namespace names.
     */
    public function pathError(): ?string
    {
        $segments = explode('/', $this->path());
        $valid = array_shift($segments) === 'app' && $segments !== [];

        foreach ($segments as $segment) {
            $valid = $valid && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) === 1;
        }

        return $valid ? null : sprintf('module_path must be inside app/ (got "%s").', config('blueprint.module_path'));
    }

    public function path(): string
    {
        return trim((string) config('blueprint.module_path'), '/');
    }

    public function namespace(): string
    {
        return 'App\\'.str_replace('/', '\\', substr($this->path(), strlen('app/')));
    }

    /**
     * The existing module folder for $input (case-insensitive), or a refusal message.
     *
     * @return array{0: ?string, 1: ?string} [module folder, error]
     */
    public function locate(string $input): array
    {
        $module = self::entry(base_path($this->path()), $input);

        if ($module === null || ! is_dir(base_path($this->path().'/'.$module))) {
            return [null, "Module \"{$input}\" not found in {$this->path()}. Create it with php artisan make:module {$input}."];
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $module) !== 1) {
            $suggestion = ModuleName::normalize($module) ?? 'Orders';

            return [null, "Module folder \"{$module}\" is not a valid PHP namespace name. Rename it (e.g. {$suggestion}) and try again."];
        }

        return [$module, null];
    }

    /**
     * The existing file or directory in $directory matching $name case-insensitively.
     */
    public static function entry(string $directory, string $name): ?string
    {
        if (! is_dir($directory)) {
            return null;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..' && strcasecmp($entry, $name) === 0) {
                return $entry;
            }
        }

        return null;
    }
}
