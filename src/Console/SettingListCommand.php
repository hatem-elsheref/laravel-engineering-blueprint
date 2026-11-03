<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Console\Concerns\RunsInFlagContext;
use Best\Blueprint\Settings\SettingManager;
use Illuminate\Console\Command;

class SettingListCommand extends Command
{
    use RunsInFlagContext;

    protected $signature = 'setting:list {--tenant= : Tenant id (tenant-saas)}';

    protected $description = 'List settings and their values (platform admin)';

    public function handle(SettingManager $settings): int
    {
        return $this->inFlagContext(function () use ($settings): void {
            $rows = [];

            foreach ($settings->definitions() as $key => $definition) {
                $rows[] = [$key, json_encode($settings->get($key)), $definition['type'], $definition['editable_by'] ?? 'platform'];
            }

            $this->table(['Setting', 'Value', 'Type', 'Editable by'], $rows);
        });
    }
}
