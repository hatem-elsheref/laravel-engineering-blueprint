<?php

namespace Best\Blueprint\Permissions;

use Spatie\Permission\PermissionServiceProvider;

class PermissionsDetector
{
    /** spatie/laravel-permission is installed and its service provider is loaded. */
    public function spatie(): bool
    {
        return class_exists(PermissionServiceProvider::class) && app()->providerIsLoaded(PermissionServiceProvider::class);
    }
}
