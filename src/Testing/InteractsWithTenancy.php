<?php

namespace Best\Blueprint\Testing;

use Best\Blueprint\Tenancy\TenantDomains;
use Best\Blueprint\Tenancy\TenantSetup;
use Illuminate\Database\Eloquent\Model;

/**
 * For tests of tenant code: creates a real tenant (record, domain, database, tenant migrations),
 * starts tenancy, and removes everything when the test's application is destroyed.
 */
trait InteractsWithTenancy
{
    /** @var list<Model> */
    private array $testTenants = [];

    private ?string $testTenantDomain = null;

    protected function createTestTenant(string $id = 'test'): Model
    {
        $domain = app(TenantDomains::class)->forTenant($id);

        app(TenantSetup::class)->run($id, $domain, 'tenant_test_'.str_replace('-', '_', $id));

        if ($this->testTenants === []) {
            $this->beforeApplicationDestroyed(fn () => $this->cleanUpTestTenants());
        }

        $tenant = tenancy()->find($id);
        $this->testTenants[] = $tenant;
        $this->testTenantDomain = $domain;

        tenancy()->initialize($tenant);

        return $tenant;
    }

    protected function tenantUrl(string $path): string
    {
        return app(TenantDomains::class)->url((string) $this->testTenantDomain, $path);
    }

    protected function cleanUpTestTenants(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        foreach ($this->testTenants as $tenant) {
            $tenant->database()->manager()->deleteDatabase($tenant);
            $tenant->domains()->delete();
            $tenant::withoutEvents(fn () => $tenant->delete());
        }

        $this->testTenants = [];
    }
}
