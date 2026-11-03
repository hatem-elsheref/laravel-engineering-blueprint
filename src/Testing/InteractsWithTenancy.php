<?php

namespace Best\Blueprint\Testing;

use Best\Blueprint\Tenancy\TenantDomains;
use Best\Blueprint\Tenancy\TenantSetup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\ParallelTesting;
use RuntimeException;
use Stancl\Tenancy\Events\TenancyInitialized;

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
        if (! Event::hasListeners(TenancyInitialized::class)) {
            throw new RuntimeException('Tenancy is not bootstrapped (App\Providers\TenancyServiceProvider is not registered), so tenant migrations would hit the central database.');
        }

        // Parallel test workers each get their own tenant and database.
        $token = ParallelTesting::token();
        $id = $token ? "{$id}-{$token}" : $id;
        $domain = app(TenantDomains::class)->forTenant($id);

        // "blueprint_test_" so a real tenant's default database (tenant_<name>) can never collide.
        app(TenantSetup::class)->run($id, $domain, 'blueprint_test_'.str_replace('-', '_', $id));

        if ($this->testTenants === []) {
            // First, so tenancy is over before other teardown work (e.g. DatabaseMigrations' rollback).
            array_unshift($this->beforeApplicationDestroyedCallbacks, $this->cleanUpTestTenants(...));
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
