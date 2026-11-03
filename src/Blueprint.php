<?php

namespace Best\Blueprint;

use Composer\InstalledVersions;

final class Blueprint
{
    public const NAME = 'best/laravel-engineering-blueprint';

    public const VERSION = '0.6.0';

    /**
     * The installed package version when Composer knows a tagged release, otherwise VERSION.
     */
    public static function version(): string
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled(self::NAME)) {
            return self::VERSION;
        }

        return self::normalizeVersion(InstalledVersions::getPrettyVersion(self::NAME));
    }

    /**
     * A tagged version without its "v" prefix; VERSION for branches and unversioned installs.
     */
    public static function normalizeVersion(?string $installed): string
    {
        if ($installed === null || str_starts_with($installed, 'dev-') || str_contains($installed, 'no-version-set')) {
            return self::VERSION;
        }

        return ltrim($installed, 'vV');
    }
}
