<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Support\CrudField;
use Best\Blueprint\Support\CrudFields;
use Best\Blueprint\Support\CrudNames;
use Best\Blueprint\Support\FieldException;
use Best\Blueprint\Support\ModuleName;
use Best\Blueprint\Support\Modules;
use Best\Blueprint\Support\PestDetector;
use Best\Blueprint\Support\RouteFile;
use Best\Blueprint\Support\StubRenderer;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

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
        $module = Modules::entry(base_path($modules->path()), $moduleInput);

        if ($module === null || ! is_dir(base_path($modules->path().'/'.$module))) {
            return $this->refuse("Module \"{$moduleInput}\" not found in {$modules->path()}. Create it with php artisan make:module {$moduleInput}.");
        }

        $entity = ModuleName::normalize((string) $this->argument('entity'));

        try {
            $fields = CrudFields::parse($this->option('fields'));
        } catch (FieldException $e) {
            return $this->refuse($e->getMessage());
        }

        $names = CrudNames::for($entity, $module, $modules->namespace().'\\'.$module, $modules->path().'/'.$module);
        $routesPath = base_path($names->modulePath.'/routes/api.php');
        $generated = $this->render($stubs, $names, $fields);

        if (! $pest->installed()) {
            unset($generated[$names->files()['test']]);
        }

        foreach ($generated as $path => $contents) {
            $files->ensureDirectoryExists(dirname(base_path($path)));
            $files->put(base_path($path), $contents);
            $this->line("created {$path}");
        }

        if (! $pest->installed()) {
            $this->warn("Skipped {$names->files()['test']}: Pest is not installed (composer require pestphp/pest --dev --with-all-dependencies && ./vendor/bin/pest --init).");
        }

        $files->put($routesPath, RouteFile::addResource(
            $files->get($routesPath),
            "{$names->moduleNamespace}\\Controllers\\{$names->entity}Controller",
            $names->uri,
            "{$names->entity}Controller",
        ));
        $this->line("added route to {$names->modulePath}/routes/api.php");

        $this->line("Note: routes are protected by {$names->entity}Policy (guests get 403). Add auth middleware (e.g. auth:sanctum) in routes/api.php for token APIs.");
        $this->line("Next: php artisan migrate && php artisan test --filter={$names->entity}ApiTest");

        return self::SUCCESS;
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
            $paths['test'] => $stubs->render('test.stub', $common + [
                'imports' => $this->imports([$model, 'App\\Models\\User', 'Illuminate\\Foundation\\Testing\\RefreshDatabase']),
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
            ."    \$this->actingAs(\$this->user)->postJson('/api/{$names->uri}', [])\n"
            ."        ->assertUnprocessable()\n"
            ."        ->assertJsonValidationErrors([".implode(', ', $required)."]);\n"
            ."});\n";
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
