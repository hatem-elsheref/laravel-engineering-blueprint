<?php

namespace Best\Blueprint\Permissions;

use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;

/**
 * Gives every tenant its own spatie permission cache: the cache key gets ".tenant.<id>" while a tenant
 * is active and goes back to the configured key when tenancy ends, and the registrar's in-memory
 * permissions are dropped on every switch. Works with or without stancl's CacheTenancyBootstrapper.
 * Only registered when tenancy is active and spatie is loaded.
 */
class TenantPermissionCache
{
    public function initialized(TenancyInitialized $event): void
    {
        $this->useKey(config('permission.cache.key').'.tenant.'.$event->tenancy->tenant?->getTenantKey());
    }

    public function ended(TenancyEnded $event): void
    {
        $this->useKey(null);
    }

    private function useKey(?string $key): void
    {
        $registrar = app(PermissionRegistrar::class);

        // Re-reads the cache store and the configured key. spatie 8 also drops the loaded permissions there;
        // older versions keep them, so clear them explicitly or the next tenant reuses this one's map.
        $registrar->initializeCache();
        $registrar->clearPermissionsCollection();

        if ($key !== null) {
            $registrar->cacheKey = $key;
        }
    }
}
