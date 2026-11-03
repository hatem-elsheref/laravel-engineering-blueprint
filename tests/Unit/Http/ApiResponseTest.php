<?php

use Best\Blueprint\Http\ApiResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

it('wraps scalar data in an envelope', function (): void {
    $response = ApiResponse::data(['id' => 1], 201);

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getData(true))->toBe([
            'data' => ['id' => 1],
            'meta' => [],
        ]);
});

it('adds pagination meta for resource collections', function (): void {
    $paginator = new LengthAwarePaginator([['id' => 1]], 1, 15, 1);
    $collection = JsonResource::collection($paginator);

    $payload = ApiResponse::collection($collection)->getData(true);

    expect($payload['data'])->toBe([['id' => 1]])
        ->and($payload['meta'])->toMatchArray([
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 15,
            'total' => 1,
        ]);
});
