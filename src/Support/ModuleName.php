<?php

namespace Best\Blueprint\Support;

use Illuminate\Support\Str;

final class ModuleName
{
    /**
     * StudlyCase the input; null when the result is not a valid module name.
     */
    public static function normalize(string $input): ?string
    {
        $name = Str::studly(trim($input));

        return preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) === 1 ? $name : null;
    }
}
