<?php

use Best\Blueprint\Http\Idempotency\IdempotencyKey;
use Best\Blueprint\Http\Middleware\ReplayIdempotentRequests;
use Best\Blueprint\Tests\MysqlIntegration\MysqlTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(MysqlTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Schema::create('cache', function ($table) {
        $table->string('key')->primary();
        $table->mediumText('value');
        $table->integer('expiration');
    });

    Schema::create('cache_locks', function ($table) {
        $table->string('key')->primary();
        $table->string('owner');
        $table->integer('expiration');
    });

    config()->set('cache.default', 'database');
    config()->set('cache.stores.database.connection', 'testing');

    Route::middleware(['api', ReplayIdempotentRequests::class])->post('/mysql-idempotent-probe', function () {
        static $hits = 0;
        $hits++;

        return response()->json(['hits' => $hits]);
    });
});

it('replays idempotent post requests using the database cache store on mysql', function (): void {
    $headers = ['Idempotency-Key' => 'pay-1'];

    $first = $this->postJson('/mysql-idempotent-probe', ['amount' => 10], $headers)->assertOk();
    $second = $this->postJson('/mysql-idempotent-probe', ['amount' => 10], $headers)->assertOk();

    expect($first->json('hits'))->toBe(1)
        ->and($second->json('hits'))->toBe(1);
});

it('returns 409 when the idempotency key is reused with a different body on mysql cache', function (): void {
    $headers = ['Idempotency-Key' => 'pay-2'];

    $this->postJson('/mysql-idempotent-probe', ['amount' => 10], $headers)->assertOk();
    $this->postJson('/mysql-idempotent-probe', ['amount' => 11], $headers)->assertConflict();
});

it('holds the cache lock on mysql while the route runs', function (): void {
    Route::middleware(['api', ReplayIdempotentRequests::class])->post('/mysql-idempotent-lock', function () {
        $storageKey = IdempotencyKey::storageKey(request(), (string) request()->header('Idempotency-Key'));

        expect(Cache::lock($storageKey.':lock', 30)->get())->toBeFalse();

        return response()->json(['ok' => true]);
    });

    $this->postJson('/mysql-idempotent-lock', [], ['Idempotency-Key' => 'lock-1'])->assertOk();
});
