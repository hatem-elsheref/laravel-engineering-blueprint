<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Console\Concerns\RunsInFlagContext;
use Best\Blueprint\Settings\SettingAdmin;
use Illuminate\Console\Command;

class SettingSetCommand extends Command
{
    use RunsInFlagContext;

    protected $signature = 'setting:set
        {key : Setting key}
        {value : JSON value (true, 5, ["a"], "text") or plain text; put -- before negative numbers}
        {--tenant= : Tenant id (tenant-saas)}';

    protected $description = 'Set any setting (platform admin)';

    public function handle(SettingAdmin $admin): int
    {
        $key = (string) $this->argument('key');
        $raw = (string) $this->argument('value');
        $decoded = json_decode($raw, true);
        $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;

        return $this->inFlagContext(function (string $where) use ($admin, $key, $value): void {
            $admin->set($key, $value);
            $this->info("Setting \"{$key}\" updated for {$where}.");
        });
    }
}
