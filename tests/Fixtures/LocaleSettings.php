<?php

namespace Best\Blueprint\Tests\Fixtures;

final class LocaleSettings
{
    public static function definitions(): array
    {
        return ['app.language' => ['type' => 'string', 'default' => 'en', 'editable_by' => 'platform']];
    }
}
