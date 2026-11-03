<?php

namespace Best\Blueprint\Features;

use Closure;

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
            $job->fail(FeatureDisabled::key($this->key));

            return null;
        }

        return $next($job);
    }
}
