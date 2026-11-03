<?php

namespace Best\Blueprint\Tests\Fixtures;

final class TestPermissions
{
    /** @return array<string, list<string>> */
    public static function groups(): array
    {
        return ['products' => ['view', 'update'], 'orders' => ['view']];
    }

    /** @return array<string, list<string>> */
    public static function roles(): array
    {
        return ['editor' => ['products.*'], 'member' => []];
    }
}
