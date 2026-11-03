<?php

namespace Best\Blueprint\Facades;

use Best\Blueprint\Settings\SettingManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key)
 * @method static void set(string $key, mixed $value)
 * @method static array<string, mixed> all()
 *
 * @see SettingManager
 */
final class Setting extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingManager::class;
    }
}
