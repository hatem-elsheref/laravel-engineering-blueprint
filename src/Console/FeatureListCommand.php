<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Console\Concerns\RunsInFlagContext;
use Best\Blueprint\Features\FeatureManager;
use Illuminate\Console\Command;

class FeatureListCommand extends Command
{
    use RunsInFlagContext;

    protected $signature = 'feature:list {--tenant= : Tenant id (tenant-saas)}';

    protected $description = 'List features and their state (platform admin)';

    public function handle(FeatureManager $features): int
    {
        return $this->inFlagContext(function () use ($features): void {
            $rows = [];

            foreach ($features->definitions() as $key => $default) {
                $rows[] = [$key, $features->enabled($key) ? 'yes' : 'no', $default ? 'yes' : 'no'];
            }

            $this->table(['Feature', 'Enabled', 'Default'], $rows);
        });
    }
}
