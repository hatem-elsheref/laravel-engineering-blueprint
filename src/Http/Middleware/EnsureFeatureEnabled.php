<?php

namespace Best\Blueprint\Http\Middleware;

use Best\Blueprint\Features\FeatureManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware "feature:<key>": 403 when the feature is disabled for the current tenant.
 */
final class EnsureFeatureEnabled
{
    public function __construct(private readonly FeatureManager $features) {}

    public function handle(Request $request, Closure $next, string $key): Response
    {
        if (! $this->features->enabled($key)) {
            abort(403, 'This feature is not enabled.');
        }

        return $next($request);
    }
}
