<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Flags\Definitions;
use Best\Blueprint\Permissions\InvalidPermissionDefinition;
use Best\Blueprint\Permissions\PermissionsDetector;
use Best\Blueprint\Permissions\PermissionSet;
use Best\Blueprint\Permissions\PermissionSync;
use Best\Blueprint\Support\Profile;
use Illuminate\Console\Command;

class PermissionsSyncCommand extends Command
{
    protected $signature = 'permissions:sync {--prune : Delete permissions and roles that are not in the definitions} {--tenant=* : Tenant ids (tenant-saas)} {--central : Sync the central database (tenant-saas)}';

    protected $description = 'Create the defined permissions and roles, and set each defined role to exactly what the file says';

    public function handle(PermissionSync $sync): int
    {
        if (! app(PermissionsDetector::class)->spatie()) {
            return $this->refuse('Permissions need spatie/laravel-permission. Run: composer require spatie/laravel-permission');
        }

        if (! (Profile::current()?->usesTenancy() ?? false) && ($this->option('tenant') !== [] || $this->option('central'))) {
            return $this->refuse('--tenant and --central only apply to the tenant-saas profile.');
        }

        try {
            $set = PermissionSet::fromClass(Definitions::permissions());
        } catch (InvalidPermissionDefinition $e) {
            return $this->refuse($e->getMessage());
        }

        $result = $sync->run($set, (bool) $this->option('prune'));

        $this->line("added {$result->permissionsAdded} permission(s) and {$result->rolesAdded} role(s)");

        if ($result->stale !== []) {
            $this->line(($result->pruned ? 'pruned: ' : 'stale: ').implode(', ', $result->stale));

            if (! $result->pruned) {
                $this->line('Run permissions:sync --prune to delete them.');
            }
        }

        return self::SUCCESS;
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
