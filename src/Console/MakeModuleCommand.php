<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Support\ModuleName;
use Best\Blueprint\Support\FileWriter;
use Best\Blueprint\Support\Modules;
use Best\Blueprint\Support\PhpSource;
use Best\Blueprint\Support\Profile;
use Best\Blueprint\Tenancy\TenancyDetector;
use Best\Blueprint\Support\StubException;
use Best\Blueprint\Support\StubRenderer;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

class MakeModuleCommand extends Command
{
    protected $signature = 'make:module
        {name : Module name, e.g. Orders}
        {--api : Add routes/api.php}
        {--web : Add routes/web.php}
        {--central : tenant-saas only: a platform module whose routes answer on central domains}';

    protected $description = 'Create a business module in app/Modules and register its provider';

    public function handle(Filesystem $files, StubRenderer $stubs): int
    {
        $modules = new Modules;

        if (($error = $modules->profileError() ?? $modules->pathError()) !== null) {
            return $this->refuse($error);
        }

        $modulePath = $modules->path();
        $kind = $this->kind();

        if (is_int($kind)) {
            return $kind;
        }

        $input = (string) $this->argument('name');
        $name = ModuleName::normalize($input);

        if ($name === null) {
            return $this->refuse("Invalid module name \"{$input}\". Use letters and numbers, starting with a letter, e.g. Orders.");
        }

        if ($name !== $input) {
            $this->line("Using module name \"{$name}\".");
        }

        $namespace = $modules->namespace().'\\'.$name;
        $directory = base_path($modulePath.'/'.$name);

        if (($existing = Modules::entry(base_path($modulePath), $name)) !== null) {
            return $this->refuse("Module \"{$name}\" already exists at {$modulePath}/{$existing}.");
        }

        try {
            $generated = $this->render($stubs, $namespace, $name, $kind);
        } catch (StubException $e) {
            return $this->refuse($this->invalidStub($e->path, $e->reason));
        }

        if (! $this->declaresProvider($generated['Provider.php'], $namespace)) {
            return $this->refuse($this->invalidStub($stubs->path('module-provider.stub'), "no Provider class in namespace {$namespace}"));
        }

        $absolute = [];

        foreach ($generated as $relative => $contents) {
            $absolute[$directory.'/'.$relative] = $contents;
        }

        try {
            (new FileWriter($files))->write($absolute);
        } catch (Throwable $e) {
            return $this->refuse("Could not write the module ({$e->getMessage()}). Nothing was changed.");
        }

        foreach (array_keys($generated) as $relative) {
            $this->line("created {$modulePath}/{$name}/{$relative}");
        }

        $provider = $namespace.'\\Provider';

        if (ServiceProvider::addProviderToBootstrapFile($provider)) {
            $this->line("registered {$provider} in bootstrap/providers.php");
        } else {
            $this->warn("bootstrap/providers.php not found. Register {$provider} manually.");
        }

        $this->clearCaches();

        $this->line('Next: add Actions, Models and Controllers as you need them. See docs/ARCHITECTURE.md.');

        return self::SUCCESS;
    }

    private function declaresProvider(string $contents, string $namespace): bool
    {
        return PhpSource::declares($contents, $namespace, 'Provider');
    }

    private function invalidStub(?string $path, ?string $reason): string
    {
        return "Stub {$path} did not produce a valid module provider ({$reason}). Update or delete it, then re-run.";
    }

    private function clearCaches(): void
    {
        if (is_file($this->laravel->getCachedConfigPath())) {
            $this->callSilently('config:clear');
            $this->line('cleared config cache');
        }

        if (is_file($this->laravel->getCachedRoutesPath())) {
            $this->callSilently('route:clear');
            $this->line('cleared route cache');
        }
    }

    /**
     * Render every file of the module before anything is written.
     *
     * @return array<string, string> relative path => contents
     */
    private function render(StubRenderer $stubs, string $namespace, string $name, string $kind): array
    {
        $routes = array_filter(['web' => $this->option('web'), 'api' => $this->option('api')]);
        $url = Str::kebab($name);

        $files = ['Provider.php' => $stubs->render('module-provider.stub', [
            'namespace' => $namespace,
            'imports' => $this->imports($routes),
            'body' => $this->body($routes, $kind),
        ])];

        foreach (array_keys($routes) as $type) {
            $files["routes/{$type}.php"] = $stubs->render("module-routes-{$type}.stub", ['url' => $url]);
        }

        return $files;
    }

    /**
     * @param  array<string, bool>  $routes
     */
    private function imports(array $routes): string
    {
        return ($routes === [] ? '' : "use Illuminate\\Support\\Facades\\Route;\n")
            .'use Illuminate\\Support\\ServiceProvider;';
    }

    /**
     * @param  array<string, bool>  $routes
     */
    private function body(array $routes, string $kind): string
    {
        if ($routes === []) {
            return '';
        }

        $statements = match ($kind) {
            'tenant' => $this->tenantRoutes($routes),
            'central' => $this->centralRoutes($routes),
            default => implode("\n", array_intersect_key([
                'web' => "        Route::middleware('web')->group(__DIR__.'/routes/web.php');",
                'api' => "        Route::middleware('api')->prefix('api')->group(__DIR__.'/routes/api.php');",
            ], $routes)),
        };

        return "    public function boot(): void\n"
            ."    {\n"
            ."        if (\$this->app->routesAreCached()) {\n"
            ."            return;\n"
            ."        }\n\n"
            .$statements."\n"
            ."    }\n";
    }

    /**
     * Tenant routes are registered after boot, so central (domain-bound) routes come first.
     *
     * @param  array<string, bool>  $routes
     */
    private function tenantRoutes(array $routes): string
    {
        $lines = array_intersect_key([
            'web' => "            Route::middleware(['web', 'tenant'])->group(__DIR__.'/routes/web.php');",
            'api' => "            Route::middleware(['api', 'tenant'])->prefix('api')->group(__DIR__.'/routes/api.php');",
        ], $routes);

        return "        // Registered after central routes, so central (domain-bound) routes win on central hosts.\n"
            ."        \$this->app->booted(function (): void {\n"
            .implode("\n", $lines)."\n"
            ."        });";
    }

    /**
     * Central routes are bound to the central domains with one domain pattern (one registration, so
     * route names stay unique for route:cache).
     *
     * @param  array<string, bool>  $routes
     */
    private function centralRoutes(array $routes): string
    {
        $domain = "Route::domain('{central_domain}')->where(['central_domain' => \$domains])";
        $lines = array_intersect_key([
            'web' => "        {$domain}->middleware(['web', 'central'])->group(__DIR__.'/routes/web.php');",
            'api' => "        {$domain}->middleware(['api', 'central'])->prefix('api')->group(__DIR__.'/routes/api.php');",
        ], $routes);

        return "        \$domains = implode('|', array_map('preg_quote', config('tenancy.central_domains', [])));\n\n"
            .implode("\n", $lines);
    }

    /**
     * "plain" outside tenant-saas; "tenant" or "central" inside it. An int is a refusal exit code.
     */
    private function kind(): string|int
    {
        $tenantSaas = Profile::current() === Profile::TenantSaas;

        if ($this->option('central') && ! $tenantSaas) {
            return $this->refuse('--central only applies to the tenant-saas profile.');
        }

        if (! $tenantSaas) {
            return 'plain';
        }

        if (! $this->laravel->make(TenancyDetector::class)->stancl()) {
            return $this->refuse('Tenancy needs stancl/tenancy. Run: composer require stancl/tenancy:^3.10, then php artisan engineering:install --profile=tenant-saas.');
        }

        return $this->option('central') ? 'central' : 'tenant';
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
