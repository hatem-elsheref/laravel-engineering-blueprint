<?php

declare(strict_types=1);

namespace Best\Blueprint\Http\Idempotency;

use Illuminate\Http\Request;

final class IdempotencyKey
{
    /** Cache key scoped to tenant, user, route, and client idempotency header (not the body). */
    public static function storageKey(Request $request, string $clientKey): string
    {
        $user = $request->user()?->getAuthIdentifier() ?? 'guest';
        $tenant = function_exists('tenant') && tenant() ? (string) tenant('id') : 'central';
        $route = $request->route()?->getName() ?? $request->path();

        return 'leb-idempotency:'.hash('sha256', implode('|', [$tenant, (string) $user, $route, $clientKey]));
    }

    /** Fingerprint of body + query string; must match on replay or the server returns 409. */
    public static function requestFingerprint(Request $request): string
    {
        $body = hash('sha256', (string) $request->getContent());
        $query = $request->query();
        ksort($query);
        $queryHash = hash('sha256', http_build_query($query));

        return hash('sha256', $body.'|'.$queryHash);
    }
}
