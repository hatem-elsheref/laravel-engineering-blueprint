<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Support\CrudField;
use Best\Blueprint\Support\CrudFields;
use Best\Blueprint\Support\CrudNames;
use Best\Blueprint\Support\FieldException;
use Best\Blueprint\Support\FileWriter;
use Best\Blueprint\Support\ModuleName;
use Best\Blueprint\Support\Modules;
use Best\Blueprint\Support\PestDetector;
use Best\Blueprint\Support\PhpNames;
use Best\Blueprint\Support\PhpSource;
use Best\Blueprint\Support\Profile;
use Best\Blueprint\Support\RouteFile;
use Best\Blueprint\Support\StubException;
use Best\Blueprint\Support\StubRenderer;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Throwable;

class MakeCrudCommand extends Command
{
    protected $signature = 'make:crud
        {module : Module name, e.g. Orders}
        {entity : Singular entity name, e.g. Order}
        {--fields= : Comma-separated name:type list, e.g. "name:string,quantity:int,note:?string"}';

    protected $description = 'Create a JSON API CRUD for an entity in a module';

    public function handle(Filesystem $files, StubRenderer $stubs, PestDetector $pest): int
    {
        $modules = new Modules;

        if (($error = $modules->profileError() ?? $modules->pathError()) !== null) {
            return $this->refuse($error);
        }

        $moduleInput = (string) $this->argument('module');
        [$module, $moduleError] = $modules->locate($moduleInput);

        if ($moduleError !== null) {
            return $this->refuse($moduleError);
        }

        $entityInput = (string) $this->argument('entity');
        $entity = ModuleName::normalize($entityInput);

        if ($entity === null || PhpNames::isReserved($entity)) {
            return $this->refuse("Invalid entity name \"{$entityInput}\". Use a singular name, e.g. Order.");
        }

        if (Str::singular($entity) !== $entity) {
            return $this->refuse('Entity names must be singular, e.g. '.Str::singular($entity).'.');
        }

        if ($entity !== $entityInput) {
            $this->line("Using entity name \"{$entity}\".");
        }

        try {
            $fields = CrudFields::parse($this->option('fields'));
        } catch (FieldException $e) {
            return $this->refuse($e->getMessage());
        }

        $names = CrudNames::for(
            $entity,
            $module,
            $modules->namespace().'\\'.$module,
            $modules->path().'/'.$module,
            $this->isTenantModule(base_path($modules->path().'/'.$module.'/Provider.php')),
        );
        $routesPath = base_path($names->modulePath.'/routes/api.php');

        if (! $files->isFile($routesPath)) {
            $central = Profile::current() === Profile::TenantSaas && ! $this->isTenantModule(base_path($modules->path().'/'.$module.'/Provider.php'));

            return $this->refuse("Module \"{$module}\" has no routes/api.php. Add it and load it in its Provider (see docs), or create the module with make:module {$module} --api".($central ? ' --central' : '').'.');
        }

        if (($collision = $this->collision($files, $names, $routesPath)) !== null) {
            return $this->refuse("\"{$collision}\" already exists. make:crud never overwrites files.");
        }

        $controller = "{$names->entity}Controller";

        try {
            $generated = $this->render($stubs, $names, $fields);
            $routeLine = $stubs->render('route.stub', ['uri' => $names->uri, 'controller' => $controller]);
        } catch (StubException $e) {
            return $this->refuse($this->invalidStub($e->path, $e->reason));
        }

        if (! str_contains($routeLine, "{$controller}::class")) {
            return $this->refuse('Stub '.$stubs->path('route.stub')." did not produce a valid route (no {$controller}::class). Update or delete it, then re-run.");
        }

        foreach ($generated as $path => $contents) {
            if (($clash = $this->importClash($contents)) !== null) {
                return $this->refuse("Entity name \"{$entity}\" clashes with a class the generated code uses ({$clash} in {$path}). Choose another name.");
            }
        }

        foreach ($this->expectedClasses($names) as $key => [$namespace, $class, $stub]) {
            $contents = $generated[$names->files()[$key]];

            if (! PhpSource::declares($contents, $namespace, $class)) {
                return $this->refuse($this->invalidStub($stubs->path($stub), "no {$class} class in namespace {$namespace}"));
            }
        }

        if (! $pest->installed()) {
            unset($generated[$names->files()['test']]);
        }

        $routes = $files->get($routesPath);

        $writer = new FileWriter($files);
        $absolute = [];

        foreach ($generated as $path => $contents) {
            $absolute[base_path($path)] = $contents;
        }

        try {
            $writer->write($absolute);
            $files->put($routesPath, RouteFile::addResource(
                $routes,
                "{$names->moduleNamespace}\\Controllers\\{$controller}",
                $routeLine,
            ));
        } catch (Throwable $e) {
            $writer->rollback();
            $files->put($routesPath, $routes);

            return $this->refuse("Could not write the CRUD files ({$e->getMessage()}). Nothing was changed.");
        }

        foreach (array_keys($generated) as $path) {
            $this->line("created {$path}");
        }

        if (! $pest->installed()) {
            $this->warn("Skipped {$names->files()['test']}: Pest is not installed (composer require pestphp/pest --dev --with-all-dependencies && ./vendor/bin/pest --init).");
        }

        $this->line("added route to {$names->modulePath}/routes/api.php");

        $this->line("Note: routes are protected by {$names->entity}Policy (guests get 403). For token APIs add auth middleware (e.g. auth:sanctum) to the api routes in {$names->modulePath}/Provider.php, so every resource in this module gets it.");
        $this->line('Next: php artisan '.($names->tenant ? 'tenants:migrate' : 'migrate')." && php artisan test --filter={$names->entity}ApiTest");

        return self::SUCCESS;
    }

    private function collision(Filesystem $files, CrudNames $names, string $routesPath): ?string
    {
        foreach ($names->files() as $key => $path) {
            if ($key === 'migration') {
                $folder = dirname($path);
                $existing = glob(base_path("{$folder}/*_create_{$names->table}_table.php")) ?: [];

                if ($existing !== []) {
                    return $folder.'/'.basename($existing[0]);
                }

                continue;
            }

            if (($entry = Modules::entry(dirname(base_path($path)), basename($path))) !== null) {
                return dirname($path).'/'.$entry;
            }
        }

        if (preg_match('/\b'.$names->entity.'Controller\b/', $files->get($routesPath)) === 1) {
            return "{$names->modulePath}/routes/api.php";
        }

        return null;
    }

    /**
     * Class files to validate after rendering: key => [namespace, class, stub].
     *
     * @return array<string, array{string, string, string}>
     */
    private function expectedClasses(CrudNames $names): array
    {
        $ns = $names->moduleNamespace;
        $e = $names->entity;

        return [
            'model' => ["{$ns}\\Models", $e, 'model.stub'],
            'factory' => ["Database\\Factories\\{$names->module}", "{$e}Factory", 'factory.stub'],
            'store' => ["{$ns}\\Requests", "Store{$e}Request", 'request.stub'],
            'update' => ["{$ns}\\Requests", "Update{$e}Request", 'request.stub'],
            'data' => ["{$ns}\\Data", "{$e}Data", 'crud-data.stub'],
            'create' => ["{$ns}\\Actions", "Create{$e}", 'crud-create-action.stub'],
            'updateAction' => ["{$ns}\\Actions", "Update{$e}", 'crud-update-action.stub'],
            'delete' => ["{$ns}\\Actions", "Delete{$e}", 'crud-delete-action.stub'],
            'query' => ["{$ns}\\Queries", "List{$names->plural}", 'query.stub'],
            'controller' => ["{$ns}\\Controllers", "{$e}Controller", 'controller.stub'],
            'resource' => ["{$ns}\\Resources", "{$e}Resource", 'resource.stub'],
            'policy' => ["{$ns}\\Policies", "{$e}Policy", 'policy.stub'],
        ];
    }

    /**
     * A short name imported twice, or imported under the name of the class the file declares.
     */
    private function importClash(string $contents): ?string
    {
        preg_match_all('/^use ([A-Za-z0-9_\\\\]+);/m', $contents, $imports);
        preg_match('/^(?:final |abstract |readonly )*class (\w+)/m', $contents, $declared);
        $seen = isset($declared[1]) ? [strtolower($declared[1]) => true] : [];

        foreach ($imports[1] as $import) {
            $short = strtolower(class_basename($import));

            if (isset($seen[$short])) {
                return class_basename($import);
            }

            $seen[$short] = true;
        }

        return null;
    }

    private function invalidStub(?string $path, ?string $reason): string
    {
        return "Stub {$path} did not produce a valid class ({$reason}). Update or delete it, then re-run.";
    }

    /**
     * Render every file to memory.
     *
     * @param  list<CrudField>  $fields
     * @return array<string, string> base-relative path => contents
     */
    private function render(StubRenderer $stubs, CrudNames $names, array $fields): array
    {
        $ns = $names->moduleNamespace;
        $e = $names->entity;
        $hasDate = array_filter($fields, fn (CrudField $f) => $f->kind() === 'date') !== [];
        $date = $hasDate ? ['Carbon\\CarbonImmutable'] : [];
        $paths = $names->files();

        $common = [
            'entity' => $e,
            'variable' => $names->variable,
            'plural' => $names->plural,
            'uri' => $names->uri,
            'table' => $names->table,
        ];

        $render = fn (string $stub, string $namespace, array $imports, array $values = []) => $stubs->render(
            $stub,
            ['namespace' => $namespace, 'imports' => $this->imports($imports)] + $values + $common,
        );

        $model = "{$ns}\\Models\\{$e}";
        $data = "{$ns}\\Data\\{$e}Data";
        $factory = "Database\\Factories\\{$names->module}\\{$e}Factory";

        $files = [
            $paths['model'] => $render('model.stub', "{$ns}\\Models", [
                $factory,
                'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
                'Illuminate\\Database\\Eloquent\\Model',
            ], [
                'fillable' => $this->lines($fields, fn (CrudField $f) => "'{$f->column}',", 8),
                'casts' => $this->casts($fields),
            ]),
            $paths['migration'] => $stubs->render('migration.stub', $common + [
                'columns' => $this->lines($fields, fn (CrudField $f) => $f->migration().';', 12),
            ]),
            $paths['factory'] => $render('factory.stub', "Database\\Factories\\{$names->module}", [
                $model, 'Illuminate\\Database\\Eloquent\\Factories\\Factory', ...$date,
            ], [
                'definition' => $this->lines($fields, fn (CrudField $f) => "'{$f->column}' => {$f->factory()},", 12),
            ]),
            $paths['store'] => $render('request.stub', "{$ns}\\Requests", [
                $model, 'Illuminate\\Foundation\\Http\\FormRequest',
            ], [
                'class' => "Store{$e}Request",
                'ability' => "'create', {$e}::class",
                'rules' => $this->rules($fields),
            ]),
            $paths['update'] => $render('request.stub', "{$ns}\\Requests", [
                'Illuminate\\Foundation\\Http\\FormRequest',
            ], [
                'class' => "Update{$e}Request",
                'ability' => "'update', \$this->route('{$names->parameter}')",
                'rules' => $this->rules($fields),
            ]),
            $paths['data'] => $render('crud-data.stub', "{$ns}\\Data", [
                "{$ns}\\Requests\\Store{$e}Request", "{$ns}\\Requests\\Update{$e}Request", ...$date,
            ], [
                'properties' => $this->lines($fields, fn (CrudField $f) => "public {$f->type} \${$f->property},", 8),
                'arguments' => $this->lines($fields, fn (CrudField $f) => "{$f->property}: {$f->fromRequest()},", 12),
                'array' => $this->lines($fields, fn (CrudField $f) => "'{$f->column}' => \$this->{$f->property},", 12),
            ]),
            $paths['create'] => $render('crud-create-action.stub', "{$ns}\\Actions", [$data, $model, 'Illuminate\\Support\\Facades\\DB']),
            $paths['updateAction'] => $render('crud-update-action.stub', "{$ns}\\Actions", [$data, $model, 'Illuminate\\Support\\Facades\\DB']),
            $paths['delete'] => $render('crud-delete-action.stub', "{$ns}\\Actions", [$model, 'Illuminate\\Support\\Facades\\DB']),
            $paths['query'] => $render('query.stub', "{$ns}\\Queries", [$model, 'Illuminate\\Contracts\\Pagination\\LengthAwarePaginator']),
            $paths['controller'] => $render('controller.stub', "{$ns}\\Controllers", [
                "{$ns}\\Actions\\Create{$e}", "{$ns}\\Actions\\Delete{$e}", "{$ns}\\Actions\\Update{$e}",
                $data, $model, "{$ns}\\Queries\\List{$names->plural}",
                "{$ns}\\Requests\\Store{$e}Request", "{$ns}\\Requests\\Update{$e}Request",
                "{$ns}\\Resources\\{$e}Resource",
                'Illuminate\\Http\\JsonResponse', 'Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection',
                'Illuminate\\Http\\Response', 'Illuminate\\Support\\Facades\\Gate',
            ]),
            $paths['resource'] => $render('resource.stub', "{$ns}\\Resources", [
                $model, 'Illuminate\\Http\\Request', 'Illuminate\\Http\\Resources\\Json\\JsonResource',
            ], [
                'attributes' => $this->lines($fields, fn (CrudField $f) => $f->kind() === 'date'
                    ? "'{$f->column}' => \$this->{$f->column}?->toIso8601String(),"
                    : "'{$f->column}' => \$this->{$f->column},", 12),
            ]),
            $paths['policy'] => $render('policy.stub', "{$ns}\\Policies", [$model, 'App\\Models\\User']),
            $paths['test'] => $stubs->render($names->tenant ? 'test-tenant.stub' : 'test.stub', $common + [
                'imports' => $this->imports($names->tenant
                    ? [$model, 'App\\Models\\User', 'Best\\Blueprint\\Testing\\InteractsWithTenancy', 'Illuminate\\Foundation\\Testing\\DatabaseMigrations']
                    : [$model, 'App\\Models\\User', 'Illuminate\\Foundation\\Testing\\RefreshDatabase']),
                'validation' => $this->validationTest($names, $fields),
            ]),
        ];

        return $files;
    }

    /**
     * @param  list<string>  $classes
     */
    private function imports(array $classes): string
    {
        $classes = array_unique($classes);
        sort($classes);

        return implode('', array_map(fn (string $c) => "use {$c};\n", $classes));
    }

    /**
     * @param  list<CrudField>  $fields
     */
    private function lines(array $fields, callable $line, int $indent): string
    {
        return implode('', array_map(fn (CrudField $f) => str_repeat(' ', $indent).$line($f)."\n", $fields));
    }

    /**
     * @param  list<CrudField>  $fields
     */
    private function rules(array $fields): string
    {
        return $this->lines($fields, fn (CrudField $f) => "'{$f->column}' => ['".implode("', '", $f->rules())."'],", 12);
    }

    /**
     * @param  list<CrudField>  $fields
     */
    private function casts(array $fields): string
    {
        $cast = array_values(array_filter($fields, fn (CrudField $f) => $f->cast() !== null));

        if ($cast === []) {
            return '';
        }

        return "\n    /**\n     * @return array<string, string>\n     */\n    protected function casts(): array\n    {\n        return [\n"
            .$this->lines($cast, fn (CrudField $f) => "'{$f->column}' => '{$f->cast()}',", 12)
            ."        ];\n    }\n";
    }

    /**
     * @param  list<CrudField>  $fields
     */
    private function validationTest(CrudNames $names, array $fields): string
    {
        $required = array_map(fn (CrudField $f) => "'{$f->column}'", array_filter($fields, fn (CrudField $f) => ! $f->nullable));

        if ($required === []) {
            return '';
        }

        return "\nit('validates a {$names->variable}', function (): void {\n"
            .'    $this->actingAs($this->user)->postJson('.($names->tenant ? "\$this->tenantUrl('/api/{$names->uri}')" : "'/api/{$names->uri}'").", [])\n"
            ."        ->assertUnprocessable()\n"
            ."        ->assertJsonValidationErrors([".implode(', ', $required)."]);\n"
            ."});\n";
    }

    /**
     * In tenant-saas, a module is a tenant module unless its Provider uses the 'central' route group.
     */
    private function isTenantModule(string $provider): bool
    {
        return Profile::current() === Profile::TenantSaas
            && (! is_file($provider) || ! str_contains((string) file_get_contents($provider), "'central'"));
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
