<?php

namespace Best\Blueprint\Facades;

use Best\Blueprint\Features\FeatureManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool enabled(string $key)
 * @method static bool disabled(string $key)
 * @method static void ensure(string $key)
 * @method static array<string, bool> all()
 *
 * @see FeatureManager
 */
final class Feature extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FeatureManager::class;
    }
}
