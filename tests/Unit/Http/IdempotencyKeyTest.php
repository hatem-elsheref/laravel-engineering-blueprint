<?php

use Best\Blueprint\Http\Idempotency\IdempotencyKey;
use Illuminate\Http\Request;

it('builds the same storage key for the same client key regardless of body', function (): void {
    $requestA = Request::create('/pay', 'POST', [], [], [], [], '{"amount":1}');
    $requestB = Request::create('/pay', 'POST', [], [], [], [], '{"amount":2}');

    expect(IdempotencyKey::storageKey($requestA, 'key-1'))
        ->toBe(IdempotencyKey::storageKey($requestB, 'key-1'));
});

it('changes the storage key when the client idempotency key changes', function (): void {
    $request = Request::create('/pay', 'POST', content: '{"amount":1}');

    expect(IdempotencyKey::storageKey($request, 'a'))
        ->not->toBe(IdempotencyKey::storageKey($request, 'b'));
});

it('changes the fingerprint when the body changes', function (): void {
    $requestA = Request::create('/pay', 'POST', content: '{"amount":1}');
    $requestB = Request::create('/pay', 'POST', content: '{"amount":2}');

    expect(IdempotencyKey::requestFingerprint($requestA))
        ->not->toBe(IdempotencyKey::requestFingerprint($requestB));
});

it('includes query parameters in the fingerprint', function (): void {
    $withA = Request::create('/pay?ref=a', 'POST', content: '{}');
    $withB = Request::create('/pay?ref=b', 'POST', content: '{}');

    expect(IdempotencyKey::requestFingerprint($withA))
        ->not->toBe(IdempotencyKey::requestFingerprint($withB));
});

it('ignores query parameter order in the fingerprint', function (): void {
    $one = Request::create('/pay?a=1&b=2', 'POST', content: '{}');
    $two = Request::create('/pay?b=2&a=1', 'POST', content: '{}');

    expect(IdempotencyKey::requestFingerprint($one))
        ->toBe(IdempotencyKey::requestFingerprint($two));
});
