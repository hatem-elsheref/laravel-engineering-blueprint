<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Support\ModuleName;
use Best\Blueprint\Support\FileWriter;
use Best\Blueprint\Support\Modules;
use Best\Blueprint\Support\PhpSource;
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
        {--web : Add routes/web.php}';

    protected $description = 'Create a business module in app/Modules and register its provider';

    public function handle(Filesystem $files, StubRenderer $stubs): int
    {
        $modules = new Modules;

        if (($error = $modules->profileError() ?? $modules->pathError()) !== null) {
            return $this->refuse($error);
        }

        $modulePath = $modules->path();

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
            $generated = $this->render($stubs, $namespace, $name);
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
    private function render(StubRenderer $stubs, string $namespace, string $name): array
    {
        $routes = array_filter(['web' => $this->option('web'), 'api' => $this->option('api')]);
        $url = Str::kebab($name);

        $files = ['Provider.php' => $stubs->render('module-provider.stub', [
            'namespace' => $namespace,
            'imports' => $this->imports($routes),
            'body' => $this->body($routes),
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
    private function body(array $routes): string
    {
        if ($routes === []) {
            return '';
        }

        $lines = [
            'web' => "        Route::middleware('web')->group(__DIR__.'/routes/web.php');",
            'api' => "        Route::middleware('api')->prefix('api')->group(__DIR__.'/routes/api.php');",
        ];

        return "    public function boot(): void\n"
            ."    {\n"
            ."        if (\$this->app->routesAreCached()) {\n"
            ."            return;\n"
            ."        }\n\n"
            .implode("\n", array_intersect_key($lines, $routes))."\n"
            ."    }\n";
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
