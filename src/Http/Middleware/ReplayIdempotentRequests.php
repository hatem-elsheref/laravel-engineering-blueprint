<?php

declare(strict_types=1);

namespace Best\Blueprint\Http\Middleware;

use Best\Blueprint\Http\Idempotency\IdempotencyKey;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional route middleware. When the client sends an idempotency header, replays the stored response
 * for the same user/tenant/route/key and payload. Apply only to routes where duplicate POSTs are harmful.
 *
 * Requires a cache store that supports atomic locks (redis, memcached, database, file) in production.
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

        $storageKey = IdempotencyKey::storageKey($request, $clientKey);
        $fingerprint = IdempotencyKey::requestFingerprint($request);
        $ttl = (int) config('blueprint.idempotency.ttl', 86400);
        $lockSeconds = (int) config('blueprint.idempotency.lock_seconds', 30);
        $waitSeconds = (int) config('blueprint.idempotency.wait_seconds', 10);

        $cached = Cache::get($storageKey);

        if ($cached !== null) {
            return $this->replayOrConflict($cached, $fingerprint);
        }

        try {
            /** @var Response */
            return Cache::lock($storageKey.':lock', $lockSeconds)->block($waitSeconds, function () use ($request, $next, $storageKey, $fingerprint, $ttl): Response {
                $cached = Cache::get($storageKey);

                if ($cached !== null) {
                    return $this->replayOrConflict($cached, $fingerprint);
                }

                $response = $next($request);

                if ($response->isSuccessful() || $response->isRedirection()) {
                    Cache::put($storageKey, [
                        'fingerprint' => $fingerprint,
                        'status' => $response->getStatusCode(),
                        'body' => $response->getContent(),
                        'headers' => $response->headers->all(),
                    ], $ttl);
                }

                return $response;
            });
        } catch (LockTimeoutException) {
            $cached = Cache::get($storageKey);

            if ($cached !== null) {
                return $this->replayOrConflict($cached, $fingerprint);
            }

            return $this->conflictResponse('An idempotent request with this key is still in progress.');
        }
    }

    /**
     * @param  array{fingerprint?: string, status: int, body: string, headers: array<string, list<string>>}  $cached
     */
    private function replayOrConflict(array $cached, string $fingerprint): Response
    {
        if (($cached['fingerprint'] ?? '') !== $fingerprint) {
            return $this->conflictResponse('Idempotency-Key was already used with a different request payload.');
        }

        return new Response($cached['body'], $cached['status'], $cached['headers']);
    }

    private function conflictResponse(string $message): Response
    {
        return response()->json(['message' => $message], Response::HTTP_CONFLICT);
    }
}
