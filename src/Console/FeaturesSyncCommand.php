<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Console\Concerns\RunsInFlagContext;
use Best\Blueprint\Features\FeatureAdmin;
use Best\Blueprint\Support\Profile;
use Illuminate\Console\Command;

class FeaturesSyncCommand extends Command
{
    use RunsInFlagContext;

    protected $signature = 'features:sync {--tenant=* : Tenant ids (tenant-saas); all tenants when omitted}';

    protected $description = 'Add newly defined features (default state) without changing existing ones';

    public function handle(FeatureAdmin $admin): int
    {
        $profile = Profile::current();

        if ($profile === null || ! $profile->usesFeatures()) {
            return $this->refuse('Features need the saas or tenant-saas profile.');
        }

        if (! $profile->usesTenancy()) {
            if ($this->option('tenant') !== []) {
                return $this->refuse('--tenant only applies to the tenant-saas profile.');
            }

            $this->line('added '.$admin->sync().' feature(s) to the app');

            return self::SUCCESS;
        }

        $ids = $this->option('tenant') ?: tenancy()->query()->pluck('id')->all();

        foreach ($ids as $id) {
            $tenant = tenancy()->find($id);

            if ($tenant === null) {
                return $this->refuse("Tenant \"{$id}\" not found.");
            }

            $added = $tenant->run(fn () => $admin->sync());
            $this->line("added {$added} feature(s) to tenant \"{$id}\"");
        }

        return self::SUCCESS;
    }
}
