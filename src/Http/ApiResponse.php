<?php

declare(strict_types=1);

namespace Best\Blueprint\Http;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Optional JSON envelope: { "data": …, "meta": { … } }.
 * Validation errors stay Laravel's default 422 shape unless the app customizes the handler.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function data(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => (object) $meta,
        ], $status);
    }

    public static function resource(JsonResource $resource, int $status = 200): JsonResponse
    {
        return self::data($resource->resolve(request()), $status);
    }

    public static function created(JsonResource $resource): JsonResponse
    {
        return self::resource($resource, 201);
    }

    public static function collection(AnonymousResourceCollection $collection): JsonResponse
    {
        $meta = [];
        $underlying = $collection->resource;

        if ($underlying instanceof LengthAwarePaginator) {
            $meta = [
                'current_page' => $underlying->currentPage(),
                'last_page' => $underlying->lastPage(),
                'per_page' => $underlying->perPage(),
                'total' => $underlying->total(),
            ];
        }

        return self::data($collection->resolve(request()), 200, $meta);
    }
}
