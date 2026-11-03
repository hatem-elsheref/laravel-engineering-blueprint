<?php

namespace Best\Blueprint\Flags;

use Best\Blueprint\Support\Profile;
use Best\Blueprint\Tenancy\TenancyDetector;
use RuntimeException;

/**
 * Where feature and setting state lives right now: "app" in saas, the current tenant's id in
 * tenant-saas, or null when tenant-saas has no tenant (callers then fail safe).
 */
class FlagContext
{
    public function __construct(private readonly TenancyDetector $tenancy) {}

    public function key(): ?string
    {
        $profile = Profile::current();

        if ($profile === null || ! $profile->usesFeatures()) {
            throw new RuntimeException('Features and settings need the saas or tenant-saas profile.');
        }

        if (! $profile->usesTenancy()) {
            return 'app';
        }

        return $this->tenancy->stancl() && tenancy()->initialized ? (string) tenant('id') : null;
    }
}
