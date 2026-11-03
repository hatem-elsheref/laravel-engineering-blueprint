<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Console\Concerns\RunsInFlagContext;
use Best\Blueprint\Features\FeatureAdmin;
use Illuminate\Console\Command;

class FeatureDisableCommand extends Command
{
    use RunsInFlagContext;

    protected $signature = 'feature:disable {key : Feature key} {--tenant= : Tenant id (tenant-saas)}';

    protected $description = 'Disable a feature (platform admin)';

    public function handle(FeatureAdmin $admin): int
    {
        $key = (string) $this->argument('key');

        return $this->inFlagContext(function (string $where) use ($admin, $key): void {
            $admin->disable($key);
            $this->info("Feature \"{$key}\" disabled for {$where}.");
        });
    }
}
