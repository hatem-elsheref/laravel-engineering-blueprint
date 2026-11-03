<?php

namespace Best\Blueprint\Features;

use Closure;
use Illuminate\Queue\Jobs\SyncJob;

/**
 * Job middleware: a job for a disabled feature fails (visible in failed jobs) instead of running.
 *
 *     public function middleware(): array { return [new RequireFeature(Features::REPORTS)]; }
 */
final class RequireFeature
{
    public function __construct(private readonly string $key) {}

    public function handle(object $job, Closure $next): mixed
    {
        if (! app(FeatureManager::class)->enabled($this->key)) {
            // The sync driver has no failed-jobs table: fail loudly instead of silently skipping.
            if (isset($job->job) && $job->job instanceof SyncJob) {
                throw FeatureDisabled::key($this->key);
            }

            $job->fail(FeatureDisabled::key($this->key));

            return null;
        }

        return $next($job);
    }
}
