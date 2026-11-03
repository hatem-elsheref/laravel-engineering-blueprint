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
        {--fields= : Comma-separated name:type list, e.g. "name:string,quantity:int,note:?string"}
        {--feature= : Feature key that must be enabled for these routes (saas, tenant-saas)}
        {--api : JSON API (default when neither --api nor --web is given)}
        {--web : Blade pages}';

    protected $description = 'Create a CRUD for an entity in a module: JSON API (--api), Blade pages (--web) or both';

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
        $central = Profile::current() === Profile::TenantSaas && ! $names->tenant;
        $feature = $this->option('feature');
        $web = (bool) $this->option('web');
        $api = (bool) $this->option('api') || ! $web;

        if ($feature !== null && ($error = $this->featureError((string) $feature)) !== null) {
            return $this->refuse($error);
        }

        if ($feature !== null && $central) {
            return $this->refuse('Features are per tenant; central module routes have no tenant. Drop --feature or use a tenant module.');
        }

        foreach (array_keys(array_filter(['api' => $api, 'web' => $web])) as $side) {
            if (! $files->isFile(base_path("{$names->modulePath}/routes/{$side}.php"))) {
                return $this->refuse("Module \"{$module}\" has no routes/{$side}.php. Add it and load it in its Provider (see docs), or create the module with make:module {$module} --{$side}".($central ? ' --central' : '').'.');
            }
        }

        // Adding a side to an existing CRUD: the shared backend is reused, only the new side is written.
        $addingSide = $this->sharedFilesExist($names);
        $keys = [
            ...($addingSide ? [] : CrudNames::SHARED),
            ...($api ? CrudNames::API : []),
            ...($web ? CrudNames::WEB : []),
        ];

        if (($collision = $this->collision($files, $names, $keys, $api, $web)) !== null) {
            return $this->refuse("\"{$collision}\" already exists. make:crud never overwrites files.");
        }

        $routeNamePrefix = $central ? 'central.' : '';

        foreach (array_keys(array_filter(["{$routeNamePrefix}api.{$names->uri}.index" => $api, "{$routeNamePrefix}{$names->uri}.index" => $web])) as $name) {
            if (($taken = $this->takenRouteName($name)) !== null) {
                return $this->refuse($taken);
            }
        }

        $middleware = $feature === null ? '' : "\n    ->middleware('feature:{$feature}')";
        $routeLines = [];

        try {
            $selected = array_flip(array_map(fn (string $key) => $names->files()[$key], $keys));
            $generated = array_intersect_key($this->render($stubs, $names, $fields, $feature, $routeNamePrefix), $selected);

            if ($api) {
                $routeLines['api'] = $stubs->render('route.stub', ['uri' => $names->uri, 'controller' => "{$entity}Controller", 'middleware' => $middleware]);
            }

            if ($web) {
                $routeLines['web'] = $stubs->render('web-route.stub', ['uri' => $names->uri, 'controller' => "{$entity}PageController", 'middleware' => $middleware]);
            }
        } catch (StubException $e) {
            return $this->refuse($this->invalidStub($e->path, $e->reason));
        }

        if (($error = $this->routeLineError($stubs, $routeLines, $names, $feature)) !== null) {
            return $this->refuse($error);
        }

        foreach ($generated as $path => $contents) {
            if (($clash = $this->importClash($contents)) !== null) {
                return $this->refuse("Entity name \"{$entity}\" clashes with a class the generated code uses ({$clash} in {$path}). Choose another name.");
            }
        }

        foreach ($this->expectedClasses($names) as $key => [$namespace, $class, $stub]) {
            if (in_array($key, $keys, true) && ! PhpSource::declares($generated[$names->files()[$key]], $namespace, $class)) {
                return $this->refuse($this->invalidStub($stubs->path($stub), "no {$class} class in namespace {$namespace}"));
            }
        }

        // Generated tests must enable a requested feature, or the suite is red for no visible reason.
        foreach (array_keys(array_filter(['test' => $api, 'webTest' => $web])) as $key) {
            if ($feature !== null && ! str_contains($generated[$names->files()[$key]], "app(FeatureAdmin::class)->enable('{$feature}')")) {
                $stub = $stubs->path(match ($key) {
                    'test' => $names->tenant ? 'test-tenant.stub' : 'test.stub',
                    default => $names->tenant ? 'test-web-tenant.stub' : 'test-web.stub',
                });

                return $this->refuse("Stub {$stub} did not produce valid tests (no feature setup for {$feature}). Update or delete it, then re-run.");
            }
        }

        $skippedTests = [];

        if (! $pest->installed()) {
            foreach (['test', 'webTest'] as $key) {
                if (isset($generated[$names->files()[$key]])) {
                    $skippedTests[] = $names->files()[$key];
                    unset($generated[$names->files()[$key]]);
                }
            }
        }

        if ($web && ! $files->exists(base_path($this->layoutPath()))) {
            $generated[$this->layoutPath()] = $stubs->render('layout.stub', []);
        }

        $writer = new FileWriter($files);
        $absolute = [];
        $originalRoutes = [];

        foreach ($generated as $path => $contents) {
            $absolute[base_path($path)] = $contents;
        }

        try {
            $writer->write($absolute);

            foreach ($routeLines as $side => $line) {
                $routesPath = base_path("{$names->modulePath}/routes/{$side}.php");
                $originalRoutes[$routesPath] = $files->get($routesPath);
                $controller = $side === 'api' ? "{$entity}Controller" : "{$entity}PageController";
                $files->put($routesPath, RouteFile::addResource($originalRoutes[$routesPath], "{$names->moduleNamespace}\\Controllers\\{$controller}", $line));
            }
        } catch (Throwable $e) {
            $writer->rollback();

            foreach ($originalRoutes as $path => $contents) {
                $files->put($path, $contents);
            }

            return $this->refuse("Could not write the CRUD files ({$e->getMessage()}). Nothing was changed.");
        }

        if ($addingSide) {
            $this->line($web && ! $api ? "added web pages to the existing {$entity} CRUD" : "added the API to the existing {$entity} CRUD");
        }

        foreach (array_keys($generated) as $path) {
            $this->line("created {$path}");
        }

        foreach ($skippedTests as $path) {
            $this->warn("Skipped {$path}: Pest is not installed (composer require pestphp/pest --dev --with-all-dependencies && ./vendor/bin/pest --init).");
        }

        foreach (array_keys($routeLines) as $side) {
            $this->line("added route to {$names->modulePath}/routes/{$side}.php");
        }

        $this->line("Note: routes are protected by {$names->entity}Policy (guests get 403). For token APIs add auth middleware (e.g. auth:sanctum) to the api routes in {$names->modulePath}/Provider.php, so every resource in this module gets it.");

        $tests = implode('|', array_keys(array_filter(["{$entity}ApiTest" => $api, "{$entity}WebTest" => $web])));
        $migrate = $addingSide ? '' : 'php artisan '.($names->tenant ? 'tenants:migrate' : 'migrate').' && ';
        $this->line("Next: {$migrate}php artisan test --filter=".($api && $web ? "\"{$tests}\"" : $tests));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $keys
     */
    private function collision(Filesystem $files, CrudNames $names, array $keys, bool $api, bool $web): ?string
    {
        foreach ($keys as $key) {
            if (($existing = $this->existingFile($names, $key)) !== null) {
                return $existing;
            }
        }

        $controllers = array_filter(['api' => $api ? "{$names->entity}Controller" : null, 'web' => $web ? "{$names->entity}PageController" : null]);

        foreach ($controllers as $side => $controller) {
            if (preg_match('/\b'.$controller.'\b/', $files->get(base_path("{$names->modulePath}/routes/{$side}.php"))) === 1) {
                return "{$names->modulePath}/routes/{$side}.php";
            }
        }

        return null;
    }

    private function existingFile(CrudNames $names, string $key): ?string
    {
        $path = $names->files()[$key];

        if ($key === 'migration') {
            $folder = dirname($path);
            $existing = glob(base_path("{$folder}/*_create_{$names->table}_table.php")) ?: [];

            return $existing === [] ? null : $folder.'/'.basename($existing[0]);
        }

        $entry = Modules::entry(dirname(base_path($path)), basename($path));

        return $entry === null ? null : dirname($path).'/'.$entry;
    }

    private function sharedFilesExist(CrudNames $names): bool
    {
        foreach (CrudNames::SHARED as $key) {
            if ($this->existingFile($names, $key) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $routeLines  side => rendered route
     */
    private function routeLineError(StubRenderer $stubs, array $routeLines, CrudNames $names, ?string $feature): ?string
    {
        foreach ($routeLines as $side => $line) {
            $stub = $stubs->path($side === 'api' ? 'route.stub' : 'web-route.stub');
            $controller = $side === 'api' ? "{$names->entity}Controller" : "{$names->entity}PageController";

            if (! str_contains($line, "{$controller}::class")) {
                return "Stub {$stub} did not produce a valid route (no {$controller}::class). Update or delete it, then re-run.";
            }

            // API routes are named api.<uri>.* so a web resource for the same entity keeps the plain names.
            if ($side === 'api' && ! str_contains($line, "->names('api.{$names->uri}')")) {
                return "Stub {$stub} did not produce a valid route (no ->names('api.{$names->uri}')). Update or delete it, then re-run.";
            }

            // A requested feature gate must never be silently dropped (e.g. by an old published stub).
            if ($feature !== null && ! str_contains($line, "feature:{$feature}")) {
                return "Stub {$stub} did not produce a valid route (no feature:{$feature} middleware). Update or delete it, then re-run.";
            }
        }

        return null;
    }

    private function layoutPath(): string
    {
        return 'resources/views/'.str_replace('.', '/', (string) config('blueprint.layout', 'layouts.blueprint')).'.blade.php';
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
            'pageController' => ["{$ns}\\Controllers", "{$e}PageController", 'web-controller.stub'],
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
    private function render(StubRenderer $stubs, CrudNames $names, array $fields, ?string $feature = null, string $routeNamePrefix = ''): array
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
                'imports' => $this->imports([
                    ...($names->tenant
                        ? [$model, 'App\\Models\\User', 'Best\\Blueprint\\Testing\\InteractsWithTenancy', 'Illuminate\\Foundation\\Testing\\DatabaseMigrations']
                        : [$model, 'App\\Models\\User', 'Illuminate\\Foundation\\Testing\\RefreshDatabase']),
                    ...($feature === null ? [] : ['Best\\Blueprint\\Features\\FeatureAdmin']),
                ]),
                'validation' => $this->validationTest($names, $fields),
                'featureSetup' => $feature === null ? '' : "    app(FeatureAdmin::class)->enable('{$feature}');\n",
                'featureTest' => $feature === null ? '' : $this->featureTest($names, $feature),
            ]),
        ];

        $web = [
            'viewPath' => $names->viewPath(),
            'routeName' => $routeNamePrefix.$names->uri,
            'variablePlural' => Str::camel($names->plural),
            'headline' => Str::headline($e),
            'headlinePlural' => Str::headline($names->plural),
            'headlinePluralLower' => Str::lower(Str::headline($names->plural)),
            'modelClass' => '\\'.$model,
        ];
        $v = $names->variable;

        $files[$paths['pageController']] = $render('web-controller.stub', "{$ns}\\Controllers", [
            "{$ns}\\Actions\\Create{$e}", "{$ns}\\Actions\\Delete{$e}", "{$ns}\\Actions\\Update{$e}",
            $data, $model, "{$ns}\\Queries\\List{$names->plural}",
            "{$ns}\\Requests\\Store{$e}Request", "{$ns}\\Requests\\Update{$e}Request",
            'Illuminate\\Contracts\\View\\View', 'Illuminate\\Http\\RedirectResponse', 'Illuminate\\Support\\Facades\\Gate',
        ], $web);
        $files[$paths['viewIndex']] = $stubs->render('view-index.stub', $common + $web + [
            'headerCells' => $this->lines($fields, fn (CrudField $f) => '<th>'.Str::headline($f->column).'</th>', 20),
            'rowCells' => $this->lines($fields, fn (CrudField $f) => '<td>'.$f->display($v).'</td>', 24),
        ]);
        $files[$paths['viewShow']] = $stubs->render('view-show.stub', $common + $web + [
            'details' => $this->lines($fields, fn (CrudField $f) => '<dt>'.Str::headline($f->column)."</dt>\n            <dd>".$f->display($v).'</dd>', 12),
        ]);
        $files[$paths['viewCreate']] = $stubs->render('view-create.stub', $common + $web);
        $files[$paths['viewEdit']] = $stubs->render('view-edit.stub', $common + $web);
        $files[$paths['viewForm']] = $stubs->render('view-form.stub', [
            'inputs' => implode("\n\n", array_map(fn (CrudField $f) => $f->formInput($v), $fields)).($fields === [] ? '' : "\n"),
        ]);
        $files[$paths['webTest']] = $stubs->render($names->tenant ? 'test-web-tenant.stub' : 'test-web.stub', $common + [
            'imports' => $this->imports([
                ...($names->tenant
                    ? [$model, 'App\\Models\\User', 'Best\\Blueprint\\Testing\\InteractsWithTenancy', 'Illuminate\\Foundation\\Testing\\DatabaseMigrations']
                    : [$model, 'App\\Models\\User', 'Illuminate\\Foundation\\Testing\\RefreshDatabase']),
                ...($feature === null ? [] : ['Best\\Blueprint\\Features\\FeatureAdmin']),
            ]),
            'url' => $names->tenant ? '$this->tenantUrl' : 'url',
            'validation' => $this->webValidationTest($names, $fields),
            'featureSetup' => $feature === null ? '' : "    app(FeatureAdmin::class)->enable('{$feature}');\n",
            'featureTest' => $feature === null ? '' : $this->webFeatureTest($names, $feature),
        ]);

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
            .'        ->assertJsonValidationErrors(['.implode(', ', $required)."]);\n"
            ."});\n";
    }

    private function takenRouteName(string $name): ?string
    {
        $routes = app('router')->getRoutes();
        $routes->refreshNameLookups();

        return $routes->hasNamedRoute($name)
            ? "Route name \"{$name}\" is already used (for example by an older route without ->names()). Rename that route, then re-run."
            : null;
    }

    /**
     * @param  list<CrudField>  $fields
     */
    private function webValidationTest(CrudNames $names, array $fields): string
    {
        $required = array_map(fn (CrudField $f) => "'{$f->column}'", array_values(array_filter($fields, fn (CrudField $f) => ! $f->nullable)));

        if ($required === []) {
            return '';
        }

        $url = $names->tenant ? "\$this->tenantUrl('/{$names->uri}')" : "url('/{$names->uri}')";

        return "\nit('validates a {$names->variable}', function (): void {\n"
            ."    \$this->actingAs(\$this->user)->post({$url}, [])\n"
            .'        ->assertSessionHasErrors(['.implode(', ', $required)."]);\n"
            ."});\n";
    }

    private function webFeatureTest(CrudNames $names, string $feature): string
    {
        $url = $names->tenant ? "\$this->tenantUrl('/{$names->uri}')" : "url('/{$names->uri}')";

        return "\nit('is blocked when the {$feature} feature is disabled', function (): void {\n"
            ."    app(FeatureAdmin::class)->disable('{$feature}');\n\n"
            ."    \$this->actingAs(\$this->user)->get({$url})->assertForbidden();\n"
            ."});\n";
    }

    private function featureTest(CrudNames $names, string $feature): string
    {
        $url = $names->tenant ? "\$this->tenantUrl('/api/{$names->uri}')" : "'/api/{$names->uri}'";

        return "\nit('is blocked when the {$feature} feature is disabled', function (): void {\n"
            ."    app(FeatureAdmin::class)->disable('{$feature}');\n\n"
            ."    \$this->actingAs(\$this->user)->getJson({$url})->assertForbidden();\n"
            ."});\n";
    }

    private function featureError(string $feature): ?string
    {
        $profile = Profile::current();

        if ($profile === null || ! $profile->usesFeatures()) {
            return '--feature needs the saas or tenant-saas profile.';
        }

        $class = config('blueprint.features');

        if (! is_string($class) || ! class_exists($class)) {
            return 'Feature definitions not found; run php artisan engineering:install.';
        }

        return array_key_exists($feature, $class::defaults()) ? null : "Unknown feature \"{$feature}\". Add it to {$class} first.";
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
