<?php

use Best\Blueprint\Support\RouteAuth;

it('normalizes sanctum auth aliases', function (): void {
    expect(RouteAuth::normalize('sanctum'))->toBe('auth:sanctum')
        ->and(RouteAuth::normalize('auth:sanctum'))->toBe('auth:sanctum')
        ->and(RouteAuth::normalize('auth'))->toBe('auth')
        ->and(RouteAuth::normalize('bogus'))->toBeNull();
});

it('builds route middleware suffixes', function (): void {
    expect(RouteAuth::routeMiddlewareSuffix('auth:sanctum'))->toBe("\n    ->middleware('auth:sanctum')")
        ->and(RouteAuth::routeMiddlewareSuffix(null))->toBe('');
});

it('normalizes web session auth', function (): void {
    expect(RouteAuth::normalizeWeb('auth'))->toBe('auth')
        ->and(RouteAuth::normalizeWeb('web'))->toBe('auth')
        ->and(RouteAuth::normalizeWeb('sanctum'))->toBeNull();
});
