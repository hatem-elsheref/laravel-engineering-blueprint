<?php

use Best\Blueprint\Http\Middleware\ReplayIdempotentRequests;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config()->set('cache.default', 'file');
    config()->set('cache.stores.file.path', storage_path('framework/cache/idempotency-stress'));
    config()->set('blueprint.idempotency.wait_seconds', 20);

    Route::middleware(['api', ReplayIdempotentRequests::class])->post('/leb-idempotent-stress', function () {
        usleep(800_000);

        static $hits = 0;
        $hits++;

        return response()->json(['hits' => $hits]);
    });
});

it('executes once when two posts overlap the same idempotency key', function (): void {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl extension required for concurrent idempotency stress.');
    }

    $resultPath = storage_path('framework/cache/idempotency-stress-child.json');
    @unlink($resultPath);

    $pid = pcntl_fork();

    if ($pid === -1) {
        $this->fail('pcntl_fork failed');
    }

    if ($pid === 0) {
        usleep(150_000);
        $response = test()->postJson('/leb-idempotent-stress', ['amount' => 1], ['Idempotency-Key' => 'stress-overlap']);
        file_put_contents($resultPath, (string) $response->getContent());
        exit(0);
    }

    $parent = $this->postJson('/leb-idempotent-stress', ['amount' => 1], ['Idempotency-Key' => 'stress-overlap'])->assertOk();
    pcntl_wait($status);

    expect(is_file($resultPath))->toBeTrue();
    $child = json_decode((string) file_get_contents($resultPath), true);

    expect($parent->json('hits'))->toBe(1)
        ->and($child['hits'] ?? null)->toBe(1);
})->group('idempotency-stress');
