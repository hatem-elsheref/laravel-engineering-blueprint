<?php

namespace Best\Blueprint\Tenancy;

use Best\Blueprint\Support\Profile;

class TenancyDetector
{
    /** stancl/tenancy is installed. */
    public function stancl(): bool
    {
        return class_exists(\Stancl\Tenancy\Tenancy::class);
    }

    /** The project uses tenancy: stancl/tenancy is installed and the profile is tenant-saas. */
    public function active(): bool
    {
        return $this->stancl() && Profile::current() === Profile::TenantSaas;
    }
}
