<?php

namespace Best\Blueprint\Tests\Fixtures;

final class TypoPermissions
{
    /** @return array<string, list<string>> */
    public static function groups(): array
    {
        return ['products' => ['view']];
    }

    /** @return array<string, list<string>> */
    public static function roles(): array
    {
        return ['editor' => ['produtcs.*']];
    }
}
