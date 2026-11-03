<?php

namespace Best\Blueprint\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Central (platform) routes answer only on tenancy.central_domains.
 */
final class CentralDomainsOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getHost(), (array) config('tenancy.central_domains', []), true)) {
            abort(404);
        }

        // Central module routes are bound to Route::domain('{central_domain}'); controllers never need it.
        $request->route()?->forgetParameter('central_domain');

        return $next($request);
    }
}
