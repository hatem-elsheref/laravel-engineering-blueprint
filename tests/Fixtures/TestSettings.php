<?php

namespace Best\Blueprint\Tests\Fixtures;

final class TestSettings
{
    public static function definitions(): array
    {
        return [
            'app.name' => ['type' => 'string', 'default' => 'My App', 'editable_by' => 'tenant', 'ability' => 'manage-settings'],
            'app.open' => ['type' => 'string', 'default' => 'x', 'editable_by' => 'tenant'],
            'orders.limit' => ['type' => 'int', 'default' => 10, 'editable_by' => 'tenant', 'ability' => 'manage-settings'],
            'orders.rate' => ['type' => 'float', 'default' => 1.5, 'editable_by' => 'tenant', 'ability' => 'manage-settings'],
            'orders.auto_confirm' => ['type' => 'bool', 'default' => false, 'editable_by' => 'platform'],
            'orders.statuses' => ['type' => 'array', 'default' => ['new'], 'editable_by' => 'tenant', 'ability' => 'manage-settings'],
        ];
    }
}
