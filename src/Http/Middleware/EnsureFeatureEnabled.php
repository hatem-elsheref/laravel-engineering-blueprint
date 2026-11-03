<?php

namespace Best\Blueprint\Http\Middleware;

use Best\Blueprint\Features\FeatureManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware "feature:<key>[,<key>...]": 403 unless every listed feature is enabled for the current tenant.
 */
final class EnsureFeatureEnabled
{
    public function __construct(private readonly FeatureManager $features) {}

    public function handle(Request $request, Closure $next, string ...$keys): Response
    {
        // feature:orders,reports requires every listed feature; none may be ignored.
        foreach ($keys as $key) {
            if (! $this->features->enabled($key)) {
                abort(403, 'This feature is not enabled.');
            }
        }

        return $next($request);
    }
}
