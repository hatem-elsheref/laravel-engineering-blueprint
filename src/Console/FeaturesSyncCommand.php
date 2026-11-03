<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Console\Concerns\RunsInFlagContext;
use Best\Blueprint\Features\FeatureAdmin;
use Best\Blueprint\Support\Profile;
use Best\Blueprint\Tenancy\TenancyDetector;
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
            return $this->refuse('Features and settings need the saas or tenant-saas profile.');
        }

        if (! $profile->usesTenancy()) {
            if ($this->option('tenant') !== []) {
                return $this->refuse('--tenant only applies to the tenant-saas profile.');
            }

            $this->line('added '.$admin->sync().' feature(s) to the app');

            return self::SUCCESS;
        }

        if (! app(TenancyDetector::class)->stancl()) {
            return $this->refuse('Tenancy needs stancl/tenancy. Run: composer require stancl/tenancy:^3.10, then php artisan engineering:install --profile=tenant-saas.');
        }

        $ids = $this->option('tenant') ?: tenancy()->query()->pluck('id')->all();

        if ($ids === []) {
            $this->line('no tenants to sync');

            return self::SUCCESS;
        }
        $tenants = [];

        // Check every requested tenant first, so a typo doesn't leave half the tenants synced.
        foreach ($ids as $id) {
            $tenants[$id] = tenancy()->find($id) ?? null;

            if ($tenants[$id] === null) {
                return $this->refuse("Tenant \"{$id}\" not found.");
            }
        }

        $failed = false;

        foreach ($tenants as $id => $tenant) {
            try {
                $added = $tenant->run(fn () => $admin->sync());
                $this->line("added {$added} feature(s) to tenant \"{$id}\"");
            } catch (\Throwable $e) {
                $failed = true;
                $this->error("Tenant \"{$id}\": {$e->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
