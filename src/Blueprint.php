<?php

namespace Best\Blueprint;

use Composer\InstalledVersions;

final class Blueprint
{
    public const NAME = 'best/laravel-engineering-blueprint';

    public const VERSION = '0.3.0';

    /**
     * The installed package version when Composer knows a tagged release, otherwise VERSION.
     */
    public static function version(): string
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled(self::NAME)) {
            return self::VERSION;
        }

        $installed = InstalledVersions::getPrettyVersion(self::NAME);

        if ($installed === null || str_starts_with($installed, 'dev-') || str_contains($installed, 'no-version-set')) {
            return self::VERSION;
        }

        return $installed;
    }
}
