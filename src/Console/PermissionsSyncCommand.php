<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Flags\Definitions;
use Best\Blueprint\Permissions\InvalidPermissionDefinition;
use Best\Blueprint\Permissions\PermissionsDetector;
use Best\Blueprint\Permissions\PermissionSet;
use Best\Blueprint\Permissions\PermissionSync;
use Best\Blueprint\Permissions\SyncResult;
use Best\Blueprint\Support\Profile;
use Best\Blueprint\Tenancy\TenancyDetector;
use Illuminate\Console\Command;
use Throwable;

class PermissionsSyncCommand extends Command
{
    protected $signature = 'permissions:sync {--prune : Delete permissions and roles that are not in the definitions} {--tenant=* : Tenant ids (tenant-saas); all tenants when omitted} {--central : Sync the central database with CentralPermissions (tenant-saas)}';

    protected $description = 'Create the defined permissions and roles, and set each defined role to exactly what the file says';

    public function handle(PermissionSync $sync): int
    {
        if (! app(PermissionsDetector::class)->spatie()) {
            return $this->refuse('Permissions need spatie/laravel-permission. Run: composer require spatie/laravel-permission');
        }

        if (($unsupported = app(PermissionsDetector::class)->unsupported()) !== null) {
            return $this->refuse($unsupported);
        }

        $tenancy = Profile::current()?->usesTenancy() ?? false;
        $tenantIds = (array) $this->option('tenant');
        $central = (bool) $this->option('central');
        $prune = (bool) $this->option('prune');

        if (! $tenancy && ($tenantIds !== [] || $central)) {
            return $this->refuse('--tenant and --central only apply to the tenant-saas profile.');
        }

        if ($central && $tenantIds !== []) {
            return $this->refuse('--central and --tenant cannot be combined: the central database and tenant databases have separate definitions. Run them separately.');
        }

        try {
            // Tenant databases only ever receive Permissions, the central database only CentralPermissions.
            $set = PermissionSet::fromClass($central ? Definitions::centralPermissions() : Definitions::permissions());
        } catch (InvalidPermissionDefinition $e) {
            return $this->refuse($e->getMessage());
        }

        if (! $tenancy) {
            $this->report('', '', $sync->run($set, $prune));

            return self::SUCCESS;
        }

        if (! app(TenancyDetector::class)->stancl()) {
            return $this->refuse('Tenancy needs stancl/tenancy. Run: composer require stancl/tenancy:^3.10, then php artisan engineering:install --profile=tenant-saas.');
        }

        if (tenancy()->initialized) {
            return $this->refuse('Run permissions:sync from the central app, not inside a tenant.');
        }

        if ($central) {
            $this->report('central: ', '--central ', $sync->run($set, $prune));

            return self::SUCCESS;
        }

        $ids = $tenantIds ?: tenancy()->query()->pluck('id')->all();

        if ($ids === []) {
            $this->line('no tenants to sync');

            return self::SUCCESS;
        }

        $tenants = [];

        // Check every requested tenant first, so a typo doesn't leave half the tenants synced.
        foreach ($ids as $id) {
            $tenants[$id] = tenancy()->find($id);

            if ($tenants[$id] === null) {
                return $this->refuse("Tenant \"{$id}\" not found.");
            }
        }

        $failed = false;

        foreach ($tenants as $id => $tenant) {
            try {
                $this->report("tenant \"{$id}\": ", "--tenant={$id} ", $tenant->run(fn () => $sync->run($set, $prune)));
            } catch (Throwable $e) {
                // stancl's run() leaves tenancy on when the callback throws; end() is a no-op otherwise.
                tenancy()->end();

                $failed = true;
                $this->error("Tenant \"{$id}\": {$e->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @param  string  $side  Options naming the synced side, so the --prune hint targets the same database(s). */
    private function report(string $prefix, string $side, SyncResult $result): void
    {
        $this->line("{$prefix}added {$result->permissionsAdded} permission(s) and {$result->rolesAdded} role(s)");

        if ($result->stale !== []) {
            $this->line($prefix.($result->pruned ? 'pruned: ' : 'stale: ').implode(', ', $result->stale));

            if (! $result->pruned) {
                $this->line("Run permissions:sync {$side}--prune to delete them.");
            }
        }
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
