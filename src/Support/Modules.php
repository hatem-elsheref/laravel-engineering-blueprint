<?php

namespace Best\Blueprint\Support;

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
