<?php

namespace Best\Blueprint\Settings;

use Best\Blueprint\Flags\AdminGuard;

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

        $this->settings->write($key, $this->settings->convert($key, $definition, $value));
    }
}
