<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Tenancy\TenancyDetector;
use Best\Blueprint\Tenancy\TenantDomains;
use Best\Blueprint\Tenancy\TenantSetup;
use Best\Blueprint\Tenancy\TenantSetupException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;

class TenantCreateCommand extends Command
{
    protected $signature = 'tenant:create
        {name : Tenant id, lowercase letters, numbers and dashes}
        {--domain= : Full domain, defaults to <name>.<base domain>}
        {--database= : Database name, defaults to tenant_<name>}';

    protected $description = 'Create a tenant: records, domain, database, migrations and seed data (safe to re-run)';

    public function handle(TenancyDetector $detector, TenantDomains $domains, TenantSetup $setup): int
    {
        $model = config('tenancy.tenant_model');

        if (! $detector->active() || ! is_string($model) || ! class_exists($model)) {
            return $this->refuse('tenant:create needs the tenant-saas profile with stancl/tenancy set up. Run php artisan engineering:install --profile=tenant-saas.');
        }

        if (! Event::hasListeners(\Stancl\Tenancy\Events\TenancyInitialized::class)) {
            return $this->refuse('Tenancy is not bootstrapped (App\Providers\TenancyServiceProvider is not registered), so tenant migrations would hit the central database. Run php artisan engineering:install --profile=tenant-saas.');
        }

        if (tenancy()->initialized) {
            return $this->refuse('Run tenant:create from the central app, not inside a tenant.');
        }

        $name = (string) $this->argument('name');
        $domain = (string) ($this->option('domain') ?: $domains->forTenant($name));
        $database = (string) ($this->option('database') ?: 'tenant_'.str_replace('-', '_', $name));

        if (($error = $domains->nameError($name)) !== null) {
            return $this->refuse($error);
        }

        $this->line('✓ Validate name');

        if (($error = $domains->domainError($domain) ?? $domains->databaseError($database)) !== null) {
            return $this->refuse($error);
        }

        $this->line('✓ Validate domain');

        if (($error = $this->duplicateError($model, $name, $domain, $database)) !== null) {
            return $this->refuse($error);
        }

        $this->line('✓ Check duplicates');

        try {
            $results = $setup->run($name, $domain, $database);
        } catch (TenantSetupException $e) {
            $this->error("✗ {$e->step}: {$e->getMessage()}");
            $this->line('Fix the problem and run the same command again; finished steps are skipped.');

            return self::FAILURE;
        }

        foreach ($results as $result) {
            $this->line(match ($result['status']) {
                'done' => "✓ {$result['step']}",
                'already' => "– {$result['step']} (already done)",
                default => "– {$result['step']} (skipped: {$result['note']})",
            });
        }

        $this->line('– Create default features (skipped: coming in Phase 7)');
        $this->line('– Create default settings (skipped: coming in Phase 7)');
        $this->info("Tenant \"{$name}\" is ready at ".$domains->url($domain));

        return self::SUCCESS;
    }

    private function duplicateError(string $model, string $name, string $domain, string $database): ?string
    {
        $domainModel = config('tenancy.domain_model');
        $owner = $domainModel::query()->where('domain', $domain)->value('tenant_id');

        if ($owner !== null && $owner !== $name) {
            return "Domain \"{$domain}\" already belongs to tenant \"{$owner}\".";
        }

        $tenant = $model::find($name);

        if ($tenant === null) {
            return null;
        }

        $domainsOfTenant = $tenant->domains()->pluck('domain')->all();
        $sameDatabase = $tenant->getAttribute('tenancy_db_name') === $database;
        $sameDomain = $domainsOfTenant === [] || in_array($domain, $domainsOfTenant, true);

        if ($sameDatabase && $sameDomain) {
            return null;
        }

        return sprintf(
            'Tenant "%s" already exists with database "%s" and domain "%s".',
            $name,
            $tenant->getAttribute('tenancy_db_name'),
            $domainsOfTenant[0] ?? 'none',
        );
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
