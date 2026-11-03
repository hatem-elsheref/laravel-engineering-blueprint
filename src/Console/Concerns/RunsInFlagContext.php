<?php

namespace Best\Blueprint\Console\Concerns;

use Best\Blueprint\Support\Profile;
use Best\Blueprint\Tenancy\TenancyDetector;
use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * Platform-admin commands run against the app (saas) or one tenant (tenant-saas, --tenant=<id>).
 */
trait RunsInFlagContext
{
    /**
     * Runs $work inside the right context; $work receives a label like 'the app' or 'tenant "acme"'.
     */
    protected function inFlagContext(Closure $work): int
    {
        $profile = Profile::current();

        if ($profile === null || ! $profile->usesFeatures()) {
            return $this->refuse('Features and settings need the saas or tenant-saas profile.');
        }

        $tenant = $this->option('tenant');

        if (! $profile->usesTenancy()) {
            if ($tenant) {
                return $this->refuse('--tenant only applies to the tenant-saas profile.');
            }

            return $this->attempt(fn () => $work('the app'));
        }

        if (! app(TenancyDetector::class)->stancl()) {
            return $this->refuse('Tenancy needs stancl/tenancy. Run: composer require stancl/tenancy:^3.10, then php artisan engineering:install --profile=tenant-saas.');
        }

        if (! $tenant) {
            return $this->refuse('--tenant is required in the tenant-saas profile.');
        }

        $model = tenancy()->find($tenant);

        if ($model === null) {
            return $this->refuse("Tenant \"{$tenant}\" not found.");
        }

        return $this->attempt(fn () => $model->run(fn () => $work("tenant \"{$tenant}\"")));
    }

    private function attempt(Closure $work): int
    {
        try {
            $work();
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->refuse($e->getMessage());
        }

        return self::SUCCESS;
    }

    protected function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
