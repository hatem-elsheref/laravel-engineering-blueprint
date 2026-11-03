<?php

namespace Best\Blueprint\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pushed onto the "web" group in tenant-saas. A session belongs to the tenant (or the central app)
 * where it started; the same cookie on another tenant or on the central domain is refused, whatever
 * the session driver (a shared redis/file store would otherwise log users in across tenants).
 */
final class ScopeSessionToDomain
{
    public const KEY = '_tenant_id';

    public const CENTRAL = '__central__';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $owner = tenancy()->initialized ? (string) tenant()->getTenantKey() : self::CENTRAL;
        $session = $request->session();

        if (! $session->has(self::KEY)) {
            $session->put(self::KEY, $owner);
        } elseif ($session->get(self::KEY) !== $owner) {
            abort(403, __('This session belongs to another site.'));
        }

        return $next($request);
    }
}
