<?php

namespace Best\Blueprint\Flags;

/**
 * Feature and platform-setting writes are for platform admins (docs/FEATURES.md §4.6–4.7):
 * never during an HTTP request, and in tenant-saas only inside a tenant (never the central DB).
 */
class AdminGuard
{
    public function __construct(private readonly FlagContext $context) {}

    public function check(): void
    {
        if ($this->inHttpRequest()) {
            throw new AdminOnly('Feature and setting changes are for platform admins: use the feature:* and setting:* console commands.');
        }

        if ($this->context->key() === null) {
            throw new AdminOnly('No tenant is active; run this inside a tenant (e.g. tenancy()->find($id)->run(...)).');
        }
    }

    protected function inHttpRequest(): bool
    {
        return ! app()->runningInConsole() || app(HttpDispatch::class)->active;
    }
}
