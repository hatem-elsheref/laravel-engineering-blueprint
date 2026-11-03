<?php

declare(strict_types=1);

namespace Best\Blueprint\Support;

use Illuminate\Http\Request;

final class ListPerPage
{
    public static function resolve(?int $requested = null): int
    {
        $default = (int) config('blueprint.pagination.per_page', 15);
        $max = (int) config('blueprint.pagination.max_per_page', 100);
        $perPage = $requested ?? $default;

        return min(max(1, $perPage), max(1, $max));
    }

    public static function fromRequest(Request $request): int
    {
        $raw = $request->input('per_page');

        return self::resolve(is_numeric($raw) ? (int) $raw : null);
    }
}
