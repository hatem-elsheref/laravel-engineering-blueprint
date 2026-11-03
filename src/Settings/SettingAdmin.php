<?php

namespace Best\Blueprint\Settings;

use Best\Blueprint\Flags\AdminGuard;
use Best\Blueprint\Flags\Rows;

/**
 * Platform-admin writes (console only): any setting, still type-checked.
 */
class SettingAdmin
{
    public function __construct(
        private readonly SettingManager $settings,
        private readonly AdminGuard $guard,
    ) {}

    public function set(string $key, mixed $value): void
    {
        $this->guard->check();
        $definition = $this->settings->definition($key);

        Rows::upsert('settings', $key, ['value' => json_encode($this->settings->convert($key, $definition, $value))]);
        $this->settings->forget();
    }
}
