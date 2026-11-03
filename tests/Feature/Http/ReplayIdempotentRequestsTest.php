<?php

use Best\Blueprint\Http\Idempotency\IdempotencyKey;
use Best\Blueprint\Http\Middleware\ReplayIdempotentRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config()->set('cache.default', 'file');
    config()->set('cache.stores.file.path', storage_path('framework/cache/idempotency-test'));

    Route::middleware(['api', ReplayIdempotentRequests::class])->post('/leb-idempotent-probe', function () {
        static $hits = 0;
        $hits++;

        return response()->json(['hits' => $hits]);
    });
});

it('replays the stored response when the idempotency key and payload match', function (): void {
    $headers = ['Idempotency-Key' => 'order-42'];

    $first = $this->postJson('/leb-idempotent-probe', ['amount' => 10], $headers)->assertOk();
    $second = $this->postJson('/leb-idempotent-probe', ['amount' => 10], $headers)->assertOk();

    expect($first->json('hits'))->toBe(1)
        ->and($second->json('hits'))->toBe(1);
});

it('returns 409 when the idempotency key is reused with a different body', function (): void {
    $headers = ['Idempotency-Key' => 'order-43'];

    $this->postJson('/leb-idempotent-probe', ['amount' => 10], $headers)->assertOk()->assertJson(['hits' => 1]);
    $this->postJson('/leb-idempotent-probe', ['amount' => 11], $headers)
        ->assertConflict()
        ->assertJson(['message' => 'Idempotency-Key was already used with a different request payload.']);
});

it('returns 409 when the idempotency key is reused with different query parameters', function (): void {
    $headers = ['Idempotency-Key' => 'order-44'];

    $this->postJson('/leb-idempotent-probe?version=1', ['amount' => 10], $headers)->assertOk()->assertJson(['hits' => 1]);
    $this->postJson('/leb-idempotent-probe?version=2', ['amount' => 10], $headers)
        ->assertConflict()
        ->assertJson(['message' => 'Idempotency-Key was already used with a different request payload.']);
});

it('holds an exclusive cache lock while the route runs', function (): void {
    Route::middleware(['api', ReplayIdempotentRequests::class])->post('/leb-idempotent-lock-probe', function () {
        $storageKey = IdempotencyKey::storageKey(request(), (string) request()->header('Idempotency-Key'));

        expect(Cache::lock($storageKey.':lock', 30)->get())->toBeFalse();

        return response()->json(['ok' => true]);
    });

    $this->postJson('/leb-idempotent-lock-probe', [], ['Idempotency-Key' => 'lock-probe'])->assertOk();
});
