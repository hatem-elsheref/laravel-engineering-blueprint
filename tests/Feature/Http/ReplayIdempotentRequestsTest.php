<?php

use Best\Blueprint\Http\Middleware\ReplayIdempotentRequests;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config()->set('cache.default', 'array');

    Route::middleware(['api', ReplayIdempotentRequests::class])->post('/leb-idempotent-probe', function () {
        static $hits = 0;
        $hits++;

        return response()->json(['hits' => $hits]);
    });
});

it('replays the stored response when the idempotency key matches', function (): void {
    $headers = ['Idempotency-Key' => 'order-42'];

    $first = $this->postJson('/leb-idempotent-probe', ['amount' => 10], $headers)->assertOk();
    $second = $this->postJson('/leb-idempotent-probe', ['amount' => 10], $headers)->assertOk();

    expect($first->json('hits'))->toBe(1)
        ->and($second->json('hits'))->toBe(1);
});

it('does not replay when the request body differs', function (): void {
    $headers = ['Idempotency-Key' => 'order-43'];

    $this->postJson('/leb-idempotent-probe', ['amount' => 10], $headers)->assertOk()->assertJson(['hits' => 1]);
    $this->postJson('/leb-idempotent-probe', ['amount' => 11], $headers)->assertOk()->assertJson(['hits' => 2]);
});
