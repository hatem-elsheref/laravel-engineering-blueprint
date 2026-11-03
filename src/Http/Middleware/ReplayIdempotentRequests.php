<?php

declare(strict_types=1);

namespace Best\Blueprint\Http\Middleware;

use Best\Blueprint\Http\Idempotency\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional route middleware. When the client sends an idempotency header, replays the stored response
 * for the same user/tenant/route/body. Apply only to routes where duplicate POSTs are harmful.
 */
final class ReplayIdempotentRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            /** @var Response */
            return $next($request);
        }

        $header = (string) config('blueprint.idempotency.header', 'Idempotency-Key');
        $clientKey = $request->header($header);

        if (! is_string($clientKey) || $clientKey === '') {
            /** @var Response */
            return $next($request);
        }

        $cacheKey = IdempotencyKey::forRequest($request, $clientKey);
        $ttl = (int) config('blueprint.idempotency.ttl', 86400);

        /** @var array{status: int, body: string, headers: array<string, list<string>>}|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return new Response($cached['body'], $cached['status'], $cached['headers']);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response->isSuccessful() || $response->isRedirection()) {
            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'body' => $response->getContent(),
                'headers' => $response->headers->all(),
            ], $ttl);
        }

        return $response;
    }
}
