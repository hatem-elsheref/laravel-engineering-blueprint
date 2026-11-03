<?php

namespace Best\Blueprint\Tests\Fixtures;

/** Same app.language definition the settings stub generates. */
final class AppLocaleSettings
{
    public static function definitions(): array
    {
        return ['app.language' => ['type' => 'string', 'default' => config('app.locale', 'en'), 'editable_by' => 'tenant', 'ability' => 'manage-settings']];
    }
}
