<?php

declare(strict_types=1);

namespace Best\Blueprint\Support;

final class RouteAuth
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        return match ($value) {
            'sanctum', 'auth:sanctum' => 'auth:sanctum',
            'auth' => 'auth',
            default => null,
        };
    }

    public static function fromConfig(): ?string
    {
        $configured = config('blueprint.route_auth');

        return is_string($configured) ? self::normalize($configured) : null;
    }

    public static function webFromConfig(): ?string
    {
        $configured = config('blueprint.route_auth_web');

        return is_string($configured) ? self::normalizeWeb($configured) : null;
    }

    public static function normalizeWeb(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        return match ($value) {
            'auth', 'web' => 'auth',
            default => null,
        };
    }

    public static function routeMiddlewareSuffix(?string $normalized): string
    {
        return $normalized === null ? '' : "\n    ->middleware('{$normalized}')";
    }

    /**
     * @param  list<string>  $base
     * @return list<string>
     */
    public static function mergeMiddleware(array $base, ?string $normalized): array
    {
        if ($normalized === null) {
            return $base;
        }

        return [...$base, $normalized];
    }

    /**
     * @param  list<string>  $middleware
     */
    public static function middlewareExpression(array $middleware): string
    {
        if (count($middleware) === 1) {
            return "'{$middleware[0]}'";
        }

        $quoted = implode("', '", $middleware);

        return "['{$quoted}']";
    }
}
