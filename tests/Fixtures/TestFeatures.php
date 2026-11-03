<?php

namespace Best\Blueprint\Tests\Fixtures;

final class TestFeatures
{
    public const ORDERS = 'orders';

    public const REPORTS = 'reports';

    public static function defaults(): array
    {
        return [self::ORDERS => true, self::REPORTS => false];
    }
}
