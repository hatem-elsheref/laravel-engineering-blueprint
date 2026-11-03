<?php

namespace Best\Blueprint\Tenancy;

use Best\Blueprint\Support\Profile;
use Stancl\Tenancy\Tenancy;

class TenancyDetector
{
    /** stancl/tenancy is installed and its service provider is loaded. */
    public function stancl(): bool
    {
        return class_exists(Tenancy::class) && app()->bound(Tenancy::class);
    }

    /** The project uses tenancy: stancl/tenancy is installed and the profile is tenant-saas. */
    public function active(): bool
    {
        return $this->stancl() && Profile::current() === Profile::TenantSaas;
    }
}
