<?php

namespace Best\Blueprint\Tenancy;

use Illuminate\Contracts\Console\Kernel;
use RuntimeException;
use Throwable;

/**
 * Steps 4–8 of tenant creation (docs/TENANCY.md §3.5). Every step checks whether it is already
 * done, so running again after a failure resumes where it stopped.
 */
class TenantSetup
{
    public function __construct(private readonly Kernel $artisan) {}

    /**
     * @return list<array{step: string, status: string, note: ?string}>
     *
     * @throws TenantSetupException
     */
    public function run(string $name, string $domain, string $database): array
    {
        $model = config('tenancy.tenant_model');
        $results = [];
        $tenant = null;

        $steps = [
            'Create tenant record' => function () use ($model, $name, $database, &$tenant) {
                $tenant = $model::find($name);

                if ($tenant !== null) {
                    return 'already';
                }

                // Without model events: the app's TenantCreated pipeline would otherwise run steps 6–7 itself.
                $tenant = $model::withoutEvents(fn () => $model::create(['id' => $name, 'tenancy_db_name' => $database]));

                return 'done';
            },
            'Create domain record' => function () use ($domain, &$tenant) {
                if ($tenant->domains()->where('domain', $domain)->exists()) {
                    return 'already';
                }

                $tenant->domains()->create(['domain' => $domain]);

                return 'done';
            },
            'Create database' => function () use (&$tenant) {
                $manager = $tenant->database()->manager();

                if ($manager->databaseExists($tenant->database()->getName())) {
                    return 'already';
                }

                $manager->createDatabase($tenant);

                return 'done';
            },
            'Run tenant migrations' => function () use ($name, &$tenant) {
                if (! $this->hasPendingMigrations($tenant)) {
                    return 'already';
                }

                $exit = $this->artisan->call('tenants:migrate', ['--tenants' => [$name], '--force' => true]);

                if ($exit !== 0) {
                    throw new RuntimeException(trim($this->artisan->output()) ?: "tenants:migrate exited with {$exit}");
                }

                return 'done';
            },
            'Seed tenant data' => function () use ($name, &$tenant) {
                $seeder = (string) config('blueprint.tenant_seeder', 'Database\\Seeders\\TenantSeeder');

                if (! class_exists($seeder)) {
                    return ['skipped', 'no '.$seeder];
                }

                if ($tenant->getAttribute('blueprint_seeded_at') !== null) {
                    return 'already';
                }

                $exit = $this->artisan->call('tenants:seed', ['--tenants' => [$name], '--class' => $seeder, '--force' => true]);

                if ($exit !== 0) {
                    throw new RuntimeException(trim($this->artisan->output()) ?: "tenants:seed exited with {$exit}");
                }

                $tenant->setAttribute('blueprint_seeded_at', now()->toIso8601String());
                $tenant->save();

                return 'done';
            },
        ];

        foreach ($steps as $step => $action) {
            try {
                $status = $action();
            } catch (Throwable $e) {
                // stancl's runForMultiple() leaves tenancy on when a tenant command fails.
                if (tenancy()->initialized) {
                    tenancy()->end();
                }

                throw new TenantSetupException($step, $e, $results);
            }

            [$status, $note] = is_array($status) ? $status : [$status, null];
            $results[] = ['step' => $step, 'status' => $status, 'note' => $note];
        }

        return $results;
    }

    /**
     * Whether the tenant database is missing any migration from the tenant migration paths.
     */
    private function hasPendingMigrations(object $tenant): bool
    {
        $paths = (array) (config('tenancy.migration_parameters.--path') ?? [database_path('migrations/tenant')]);

        return $tenant->run(function () use ($paths): bool {
            $migrator = app('migrator');
            $files = array_keys($migrator->getMigrationFiles($paths));

            if ($files === []) {
                return false;
            }

            if (! $migrator->repositoryExists()) {
                return true;
            }

            return array_diff($files, $migrator->getRepository()->getRan()) !== [];
        });
    }
}
