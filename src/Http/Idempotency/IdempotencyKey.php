<?php

declare(strict_types=1);

namespace Best\Blueprint\Http\Idempotency;

use Illuminate\Http\Request;

final class IdempotencyKey
{
    public static function forRequest(Request $request, string $clientKey): string
    {
        $user = $request->user()?->getAuthIdentifier() ?? 'guest';
        $tenant = function_exists('tenant') && tenant() ? (string) tenant('id') : 'central';
        $route = $request->route()?->getName() ?? $request->path();
        $body = hash('sha256', (string) $request->getContent());

        return 'leb-idempotency:'.hash('sha256', implode('|', [$tenant, (string) $user, $route, $clientKey, $body]));
    }
}
