<?php

namespace Best\Blueprint\Permissions;

use Composer\InstalledVersions;
use Spatie\Permission\PermissionServiceProvider;

class PermissionsDetector
{
    /** spatie/laravel-permission is installed and its service provider is loaded. */
    public function spatie(): bool
    {
        return class_exists(PermissionServiceProvider::class) && app()->providerIsLoaded(PermissionServiceProvider::class);
    }

    /**
     * Why the loaded spatie can't be used for permissions, or null when it can. Callers check spatie() first.
     * Refused: versions before 8 (the per-tenant permission cache and the generated code are built on 8) and
     * spatie's teams mode (roles and grants per team, which the sync, the gate and the generated code ignore).
     */
    public function unsupported(): ?string
    {
        $version = $this->installedVersion();
        $version = $version === null ? null : ltrim($version, 'vV');

        if ($version !== null && preg_match('/^\d+(\.\d+)*$/', $version) === 1 && version_compare($version, '8.0.0', '<')) {
            return "spatie/laravel-permission {$version} is not supported; permissions need ^8.0. Run: composer require spatie/laravel-permission:^8.0";
        }

        if (config('permission.teams')) {
            return 'spatie teams mode (permission.teams) is not supported. Set teams to false in config/permission.php.';
        }

        return null;
    }

    /** The installed spatie/laravel-permission version as Composer reports it (e.g. 8.3.2), or null when unknown. */
    protected function installedVersion(): ?string
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled('spatie/laravel-permission')) {
            return null;
        }

        return InstalledVersions::getPrettyVersion('spatie/laravel-permission');
    }
}
