<?php

namespace Best\Blueprint\Tenancy;

use Best\Blueprint\Support\StubRenderer;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

/**
 * Sets up stancl/tenancy for the tenant-saas profile. Every step can run again safely.
 */
class TenancySetup
{
    private const PROVIDER = 'App\\Providers\\TenancyServiceProvider';

    private const DEFAULT_CENTRAL = "    'central_domains' => [\n        '127.0.0.1',\n        'localhost',\n    ],";

    public function __construct(
        private readonly Filesystem $files,
        private readonly StubRenderer $stubs,
        private readonly TenancyDetector $detector,
    ) {}

    /**
     * @return list<string> lines to print
     */
    public function run(): array
    {
        if (! $this->detector->stancl()) {
            return [
                'Tenancy needs stancl/tenancy. Run: composer require stancl/tenancy:^3.10',
                'then re-run: php artisan engineering:install --profile=tenant-saas',
            ];
        }

        return [
            ...$this->installPackage(),
            $this->tenantModel(),
            ...$this->configure(),
            ...$this->tenantMigrations(),
            $this->registerProvider(),
            ...$this->connections(),
        ];
    }

    public function note(): string
    {
        return "Your app's routes/web.php and routes/api.php answer on every domain and win over tenant routes with the same URI. "
            .'Wrap central-only routes in Route::domain(...) for each central domain (see stancl/tenancy docs) or move them into a --central module.';
    }

    /**
     * @return list<string>
     */
    private function installPackage(): array
    {
        if ($this->files->exists(config_path('tenancy.php'))) {
            return ['already set up: config/tenancy.php'];
        }

        $routes = base_path('routes/tenant.php');
        $routesExisted = $this->files->exists($routes);

        app(Kernel::class)->call('tenancy:install');
        $lines = ['ran tenancy:install'];

        if (! $routesExisted) {
            // Replace the package's demo "/" route, which would shadow the app's home page on central domains.
            $this->files->put($routes, $this->stubs->render('tenant-routes.stub', []));
            $lines[] = 'routes/tenant.php has an empty tenant route group';
        }

        return $lines;
    }

    private function tenantModel(): string
    {
        $path = app_path('Models/Tenant.php');

        if ($this->files->exists($path)) {
            return 'already set up: app/Models/Tenant.php';
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $this->stubs->render('tenant-model.stub', []));

        return 'created app/Models/Tenant.php';
    }

    /**
     * @return list<string>
     */
    private function configure(): array
    {
        $path = config_path('tenancy.php');
        $contents = $this->files->get($path);
        $lines = [];

        $wanted = "    'tenant_model' => \\App\\Models\\Tenant::class,";

        if (str_contains($contents, $wanted)) {
            $lines[] = 'already set up: tenant_model';
        } elseif (str_contains($contents, "    'tenant_model' => Tenant::class,")) {
            $contents = str_replace("    'tenant_model' => Tenant::class,", $wanted, $contents);
            $lines[] = 'config/tenancy.php: tenant_model';
        } else {
            $lines[] = "Set 'tenant_model' => \\App\\Models\\Tenant::class in config/tenancy.php.";
        }

        $domains = TenantDomains::centralFromAppUrl((string) config('app.url'));
        $central = "    'central_domains' => [\n".implode('', array_map(fn ($d) => "        '{$d}',\n", $domains)).'    ],';

        if (str_contains($contents, $central)) {
            $lines[] = 'already set up: central_domains';
        } elseif (str_contains($contents, self::DEFAULT_CENTRAL)) {
            $contents = str_replace(self::DEFAULT_CENTRAL, $central, $contents);
            $lines[] = 'config/tenancy.php: central_domains = '.implode(', ', $domains);
        } else {
            $lines[] = "Check 'central_domains' in config/tenancy.php: it should list your central domains, e.g. ['".implode("', '", $domains)."'].";
        }

        $this->files->put($path, $contents);
        config()->set('tenancy.central_domains', $domains);

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function connections(): array
    {
        $lines = ['database queue uses the central connection ('.config('tenancy.database.central_connection').')'];

        // stancl isolates tenant cache with tags. A store without tags (database, file) fails loudly
        // inside tenants instead of sharing data between them; say so now.
        if (! cache()->store()->getStore() instanceof TaggableStore) {
            $lines[] = 'Tenant cache needs a cache store with tags (redis or memcached); CACHE_STORE='
                .config('cache.default').' fails inside tenants. Set CACHE_STORE=redis for tenant-saas.';
        }

        return $lines;
    }

    /**
     * Tenant databases need their own users (TENANCY.md §3.3).
     *
     * @return list<string>
     */
    private function tenantMigrations(): array
    {
        if ((glob(database_path('migrations/tenant/*_create_users_table.php')) ?: []) !== []) {
            return ['already set up: users migration'];
        }

        $central = glob(database_path('migrations/*_create_users_table.php')) ?: [];

        if ($central === []) {
            return ['No users migration found to copy; add one to database/migrations/tenant for tenant users.'];
        }

        $this->files->ensureDirectoryExists(database_path('migrations/tenant'));
        $this->files->copy($central[0], database_path('migrations/tenant/'.basename($central[0])));

        return ['copied users migration to database/migrations/tenant'];
    }

    private function registerProvider(): string
    {
        $path = app()->getBootstrapProvidersPath();

        if ($this->files->exists($path) && str_contains($this->files->get($path), self::PROVIDER.'::class')) {
            return 'already set up: '.self::PROVIDER;
        }

        return ServiceProvider::addProviderToBootstrapFile(self::PROVIDER)
            ? 'registered '.self::PROVIDER.' in bootstrap/providers.php'
            : 'bootstrap/providers.php not found. Register '.self::PROVIDER.' manually.';
    }
}
