<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Flags\Definitions;
use Best\Blueprint\Permissions\CrudPermissions;
use Best\Blueprint\Permissions\DefinitionsFile;
use Best\Blueprint\Permissions\InvalidPermissionDefinition;
use Best\Blueprint\Permissions\OwnedCrud;
use Best\Blueprint\Schema\EntitySchemaMeta;
use Best\Blueprint\Schema\SchemaException;
use Best\Blueprint\Support\CrudField;
use Best\Blueprint\Support\CrudFields;
use Best\Blueprint\Support\CrudNames;
use Best\Blueprint\Support\FieldException;
use Best\Blueprint\Support\FileWriter;
use Best\Blueprint\Support\GeneratedPolicy;
use Best\Blueprint\Support\ModuleName;
use Best\Blueprint\Support\Modules;
use Best\Blueprint\Support\PestDetector;
use Best\Blueprint\Support\PhpNames;
use Best\Blueprint\Support\PhpSource;
use Best\Blueprint\Support\Profile;
use Best\Blueprint\Support\RouteAuth;
use Best\Blueprint\Support\RouteFile;
use Best\Blueprint\Support\StubException;
use Best\Blueprint\Support\StubRenderer;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ReflectionClass;
use Throwable;

class MakeCrudCommand extends Command
{
    protected $signature = 'make:crud
        {module : Module name, e.g. Orders}
        {entity : Singular entity name, e.g. Order}
        {--fields= : Comma-separated name:type list, e.g. "name:string,quantity:int,note:?string"}
        {--schema= : Entity schema YAML (see docs/SCHEMA-DSL.md); do not combine with --fields}
        {--feature= : Feature key that must be enabled for these routes (saas, tenant-saas)}
        {--permissions : Policies check named permissions (needs engineering:install --permissions)}
        {--owned : Records belong to the user who creates them (needs --permissions)}
        {--open-policy : Demo policy: any signed-in user may act (not for production; default is deny-by-default)}
        {--auth= : Auth on API (sanctum, auth) and web (auth only). Defaults to blueprint.route_auth / route_auth_web}
        {--api-envelope : Wrap JSON API responses with Best\\Blueprint\\Http\\ApiResponse}
        {--api : JSON API (default when neither --api nor --web is given)}
        {--web : Blade pages}
        {--nested-under= : Parent entity in the same module for nested API routes (requires --api)}';

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

        if ($this->option('schema') !== null && $this->option('fields') !== null) {
            return $this->refuse('Use --schema or --fields, not both.');
        }

        $names = CrudNames::for(
            $entity,
            $module,
            $modules->namespace().'\\'.$module,
            $modules->path().'/'.$module,
            $this->moduleKind(base_path($modules->path().'/'.$module.'/Provider.php')) === 'tenant',
        );

        $schemaMeta = EntitySchemaMeta::empty();
        $addingSide = $this->sharedFilesExist($names);

        if ($addingSide && $this->option('schema') !== null) {
            return $this->refuse('Leave --schema out when adding a side; the existing fields are reused.');
        }

        try {
            if ($addingSide) {
                $existing = $this->existingFields($names);

                if ($this->option('fields') !== null && $this->fieldSignature(CrudFields::parse($this->option('fields'))) !== $this->fieldSignature(CrudFields::parse($existing))) {
                    return $this->refuse("--fields doesn't match the existing {$entity} CRUD ({$existing}). Leave --fields out to reuse them.");
                }

                $fields = CrudFields::parse($existing);
            } elseif ($this->option('schema') !== null) {
                [$fields, $schemaMeta] = CrudFields::fromSchema((string) $this->option('schema'), $module, $entity, $modules);
            } else {
                $fields = CrudFields::parse($this->option('fields'));
            }
        } catch (FieldException|SchemaException $e) {
            return $this->refuse($e->getMessage());
        }
        $kind = $this->moduleKind(base_path($modules->path().'/'.$module.'/Provider.php'));
        $central = $kind === 'central';
        $feature = $this->option('feature');
        $web = (bool) $this->option('web');
        $api = (bool) $this->option('api') || ! $web;

        $nestedParentNames = null;
        $nestedScopeSnippet = '';
        $nestedExtraRelations = '';

        if (($nested = $this->prepareNestedUnder($modules, $module, $entity, $names, $kind === 'tenant', $api, $web, $fields)) !== null) {
            if (is_string($nested)) {
                return $this->refuse($nested);
            }

            $fields = $nested['fields'];
            $nestedParentNames = $nested['parent'];
            $nestedScopeSnippet = $nested['scope'];
            $nestedExtraRelations = $nested['relations'];
        }

        if ($feature !== null && ($error = $this->featureError((string) $feature)) !== null) {
            return $this->refuse($error);
        }

        if ($feature !== null && $central) {
            return $this->refuse('Features are per tenant; central module routes have no tenant. Drop --feature or use a tenant module.');
        }

        $owned = (bool) $this->option('owned');

        if ($owned && ! $this->option('permissions')) {
            return $this->refuse('--owned needs --permissions.');
        }

        if ($owned && $schemaMeta->hasQueryFilters) {
            return $this->refuse('Schema query filters are not supported with --owned yet.');
        }

        $openPolicy = (bool) $this->option('open-policy');

        if ($openPolicy && $this->option('permissions')) {
            return $this->refuse('--open-policy cannot be used with --permissions.');
        }

        if ($owned && ($error = OwnedCrud::fieldError($fields)) !== null) {
            return $this->refuse($error);
        }

        $permissions = $this->option('permissions') ? new CrudPermissions($names, $central, $owned) : null;

        if ($permissions !== null && ($error = CrudPermissions::setupError($permissions->definitionsClass())) !== null) {
            return $this->refuse($error);
        }

        foreach (array_keys(array_filter(['api' => $api, 'web' => $web])) as $side) {
            if (! $files->isFile(base_path("{$names->modulePath}/routes/{$side}.php"))) {
                return $this->refuse("Module \"{$module}\" has no routes/{$side}.php. Create {$names->modulePath}/routes/{$side}.php and load it in {$names->modulePath}/Provider.php the way make:module --{$side}".($central ? ' --central' : '').' does, then re-run.');
            }
        }

        if ($kind === 'plain') {
            return $this->refuse("Module \"{$module}\" was made before tenant-saas: its routes use neither the 'tenant' nor the 'central' group. Add 'tenant' (data per tenant) or 'central' (platform) to the Route::middleware(...) calls in {$names->modulePath}/Provider.php, then re-run.");
        }

        if ($web && Str::camel($names->plural) === $names->variable) {
            return $this->refuse("Entity \"{$entity}\" has the same singular and plural form, which the web pages cannot tell apart. Choose another name (e.g. Article).");
        }

        if ($addingSide) {
            $this->line('using the existing fields: '.$this->existingFields($names));

            if (($error = $this->featureMismatch($names, $feature, $api, $web)
                ?? $this->permissionsMismatch($names, $permissions !== null, $owned)
                ?? $this->openPolicyMismatch($names, $openPolicy, $permissions !== null)) !== null) {
                return $this->refuse($error);
            }
        }
        $keys = [
            ...($addingSide ? [] : CrudNames::SHARED),
            ...($api ? CrudNames::API : []),
            ...($web ? CrudNames::WEB : []),
        ];

        if (($collision = $this->collision($files, $names, $keys, $api, $web)) !== null) {
            return $this->refuse($collision);
        }

        $routeNamePrefix = $central ? 'central.' : '';

        $routeNamesToCheck = array_filter([
            "{$routeNamePrefix}api.{$names->uri}.index" => $api && $nestedParentNames === null,
            "{$routeNamePrefix}{$names->uri}.index" => $web,
        ]);

        if ($nestedParentNames !== null && $api) {
            $routeNamesToCheck["{$routeNamePrefix}api.{$nestedParentNames->uri}.{$names->uri}.index"] = true;
        }

        foreach (array_keys($routeNamesToCheck) as $name) {
            if (($taken = $this->takenRouteName($name)) !== null) {
                return $this->refuse($taken);
            }
        }

        // The group goes into the definitions file unless it's already there (e.g. added with the other side).
        $definitions = null;

        if ($permissions !== null) {
            [$definitions, $error] = $this->definitionsEdit($files, $permissions);

            if ($error !== null) {
                return $this->refuse($error);
            }
        }

        $apiRouteAuth = $this->resolvedRouteAuth($api);
        $webRouteAuth = $this->resolvedWebRouteAuth($web);

        if ($apiRouteAuth === false || $webRouteAuth === false) {
            return $this->refuse('Invalid --auth value. API: sanctum, auth, or auth:sanctum. Web: auth (or set blueprint.route_auth_web).');
        }

        $featureMiddleware = $feature === null ? '' : "\n    ->middleware('feature:{$feature}')";
        $apiMiddleware = $featureMiddleware.RouteAuth::routeMiddlewareSuffix($apiRouteAuth);
        $webMiddleware = $featureMiddleware.RouteAuth::routeMiddlewareSuffix($webRouteAuth);
        $apiEnvelope = $api && ($this->option('api-envelope') || config('blueprint.api_envelope'));
        $routeLines = [];

        try {
            $selected = array_flip(array_map(fn (string $key) => $names->files()[$key], $keys));
            $generated = array_intersect_key($this->render(
                $stubs,
                $names,
                $fields,
                $feature,
                $routeNamePrefix,
                $permissions,
                $openPolicy,
                $schemaMeta,
                $apiEnvelope,
                $nestedScopeSnippet,
                $nestedExtraRelations,
                $apiRouteAuth,
            ), $selected);

            foreach ($schemaMeta->enumFiles as $enumFile) {
                $generated[$enumFile['path']] = $enumFile['contents'];
            }

            if ($api) {
                $routeLines['api'] = $nestedParentNames === null
                    ? $stubs->render('route.stub', ['uri' => $names->uri, 'controller' => "{$entity}Controller", 'middleware' => $apiMiddleware])
                    : $stubs->render('route-nested-api.stub', [
                        'uri' => $names->uri,
                        'parentUri' => $nestedParentNames->uri,
                        'controller' => "{$entity}Controller",
                        'middleware' => $apiMiddleware,
                    ]);
            }

            if ($web) {
                $routeLines['web'] = $stubs->render('web-route.stub', ['uri' => $names->uri, 'controller' => "{$entity}PageController", 'middleware' => $webMiddleware]);
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

        foreach ($this->expectedClasses($names, $permissions !== null, $owned, $openPolicy) as $key => [$namespace, $class, $stub]) {
            if (in_array($key, $keys, true) && ! PhpSource::declares($generated[$names->files()[$key]], $namespace, $class)) {
                return $this->refuse($this->invalidStub($stubs->path($stub), "no {$class} class in namespace {$namespace}"));
            }
        }

        // Generated tests must enable a requested feature, or the suite is red for no visible reason.
        foreach (array_keys(array_filter(['test' => $api, 'webTest' => $web])) as $key) {
            if ($feature !== null && ! str_contains($generated[$names->files()[$key]], "app(FeatureAdmin::class)->enable('{$feature}')")) {
                $stub = $stubs->path(match ($key) {
                    'test' => $this->apiTestStub($names, $permissions, $openPolicy),
                    default => $this->webTestStub($names, $permissions, $openPolicy),
                });

                return $this->refuse("Stub {$stub} did not produce valid tests (no feature setup for {$feature}). Update or delete it, then re-run.");
            }
        }

        if ($permissions !== null && ($error = $this->permissionsStubError($stubs, $generated, $names, $api, $web, $owned)) !== null) {
            return $this->refuse($error);
        }

        if ($owned && ($error = OwnedCrud::stubError($stubs, $generated, $names, $api, $web)) !== null) {
            return $this->refuse($error);
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
        $originalDefinitions = null;

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

            if ($definitions !== null && $definitions['edited'] !== null) {
                $originalDefinitions = $definitions['original'];
                $files->put($definitions['path'], $definitions['edited']);
            }
        } catch (Throwable $e) {
            $writer->rollback();

            foreach ($originalRoutes as $path => $contents) {
                $files->put($path, $contents);
            }

            if ($definitions !== null && $originalDefinitions !== null) {
                $files->put($definitions['path'], $originalDefinitions);
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

        if ($permissions !== null && $definitions !== null && $definitions['edited'] !== null) {
            $this->line("added '{$permissions->group()}' to {$definitions['relative']}");
        }

        // The group was already there (e.g. from a CRUD in another module) and lacks abilities this one checks.
        if ($permissions !== null && $definitions !== null && $definitions['missing'] !== []) {
            $missing = "'".implode("', '", $definitions['missing'])."'";
            $this->warn("The '{$permissions->group()}' group in {$definitions['relative']} has no {$missing}; another CRUD may use it. Add {$missing} to '{$permissions->group()}' in {$definitions['relative']}, then {$permissions->syncCommand()}.");
        }

        if (is_file($this->laravel->getCachedRoutesPath())) {
            $this->callSilently('route:clear');
            $this->line('cleared route cache');
        }

        if ($web) {
            $this->addTranslationKeys([Str::headline($entity), Str::headline($names->plural), ...array_map(fn (CrudField $f) => Str::headline($f->column), $fields)]);
        }

        $tokenNote = $api ? "For token APIs add auth middleware (e.g. auth:sanctum) to the api routes in {$names->modulePath}/Provider.php." : null;

        if ($permissions !== null && $definitions !== null) {
            $this->line("Next: add '{$permissions->group()}.*' (or single permissions) to a role in {$definitions['relative']}, then {$permissions->syncCommand()}");

            if ($tokenNote !== null) {
                $this->line("Note: {$tokenNote}");
            }
        } elseif ($openPolicy) {
            $this->line("Note: {$names->entity}Policy is open (--open-policy): every signed-in user may act. Guests get 403. Not for production.".($tokenNote !== null ? " {$tokenNote}" : ''));
            $this->line('Tip: omit --open-policy for deny-by-default policies, or use --permissions for named permissions.');
        } else {
            $this->line("Note: {$names->entity}Policy denies all actions until you implement it (guests and signed-in users get 403 until then).".($tokenNote !== null ? " {$tokenNote}" : ''));
            $this->line('Tip: use --permissions for production CRUD, or --open-policy for local demos only.');
        }

        $written = array_filter(["{$entity}ApiTest" => $api && ! in_array($names->files()['test'], $skippedTests, true), "{$entity}WebTest" => $web && ! in_array($names->files()['webTest'], $skippedTests, true)]);
        $steps = array_filter([
            $addingSide ? null : 'php artisan '.($names->tenant ? 'tenants:migrate' : 'migrate'),
            $written === [] ? null : 'php artisan test --filter='.(count($written) > 1 ? '"'.implode('|', array_keys($written)).'"' : array_key_first($written)),
        ]);

        if ($steps !== []) {
            $this->line('Next: '.implode(' && ', $steps));
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $keys
     */
    private function collision(Filesystem $files, CrudNames $names, array $keys, bool $api, bool $web): ?string
    {
        foreach ($keys as $key) {
            if (($existing = $this->existingFile($names, $key)) !== null) {
                return "\"{$existing}\" already exists. Nothing was changed.";
            }
        }

        $controllers = array_filter(['api' => $api ? "{$names->entity}Controller" : null, 'web' => $web ? "{$names->entity}PageController" : null]);

        foreach ($controllers as $side => $controller) {
            if (preg_match('/\b'.$controller.'\b/', $files->get(base_path("{$names->modulePath}/routes/{$side}.php"))) === 1) {
                return "\"{$names->modulePath}/routes/{$side}.php\" already has a route for {$controller}. Remove it, then re-run.";
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

            // API routes are named api.<uri>.* (or api.<parent>.<uri> when nested).
            if ($side === 'api' && ! str_contains($line, "->names('api.{$names->uri}')")
                && ! preg_match("/->names\\('api\\.[^']+\\.{$names->uri}'\\)/", $line)) {
                return "Stub {$stub} did not produce a valid route (no ->names('api.{$names->uri}')). Update or delete it, then re-run.";
            }

            // A requested feature gate must never be silently dropped (e.g. by an old published stub).
            if ($feature !== null && ! str_contains($line, "feature:{$feature}")) {
                return "Stub {$stub} did not produce a valid route (no feature:{$feature} middleware). Update or delete it, then re-run.";
            }
        }

        return null;
    }

    /**
     * Adds the entity and field names to lang/<locale>.json for every other configured language,
     * with the English text as the value to translate; existing translations are kept.
     *
     * @param  list<string>  $keys
     */
    private function addTranslationKeys(array $keys): void
    {
        foreach ((array) config('blueprint.locales', ['en']) as $locale) {
            if (! is_string($locale) || $locale === 'en') {
                continue;
            }

            $path = lang_path("{$locale}.json");
            $strings = is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
            $added = 0;

            foreach (array_unique($keys) as $key) {
                if (! array_key_exists($key, $strings)) {
                    $strings[$key] = $key;
                    $added++;
                }
            }

            if ($added > 0) {
                if (! is_dir(dirname($path))) {
                    mkdir(dirname($path), 0755, true);
                }

                file_put_contents($path, json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
                $this->line("added {$added} strings to lang/{$locale}.json — translate them");
            }
        }
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
    private function expectedClasses(CrudNames $names, bool $permissions = false, bool $owned = false, bool $openPolicy = false): array
    {
        $ns = $names->moduleNamespace;
        $e = $names->entity;

        return [
            'model' => ["{$ns}\\Models", $e, 'model.stub'],
            'factory' => ["Database\\Factories\\{$names->module}", "{$e}Factory", 'factory.stub'],
            'store' => ["{$ns}\\Requests", "Store{$e}Request", 'request.stub'],
            'update' => ["{$ns}\\Requests", "Update{$e}Request", $owned ? 'request-update-owned.stub' : 'request.stub'],
            'data' => ["{$ns}\\Data", "{$e}Data", 'crud-data.stub'],
            'create' => ["{$ns}\\Actions", "Create{$e}", $owned ? 'crud-create-action-owned.stub' : 'crud-create-action.stub'],
            'updateAction' => ["{$ns}\\Actions", "Update{$e}", 'crud-update-action.stub'],
            'delete' => ["{$ns}\\Actions", "Delete{$e}", 'crud-delete-action.stub'],
            'query' => ["{$ns}\\Queries", "List{$names->plural}", $owned ? 'query-owned.stub' : 'query.stub'],
            'controller' => ["{$ns}\\Controllers", "{$e}Controller", 'controller.stub'],
            'pageController' => ["{$ns}\\Controllers", "{$e}PageController", 'web-controller.stub'],
            'resource' => ["{$ns}\\Resources", "{$e}Resource", 'resource.stub'],
            'policy' => ["{$ns}\\Policies", "{$e}Policy", GeneratedPolicy::stub($permissions, $owned, $openPolicy)],
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
    private function render(StubRenderer $stubs, CrudNames $names, array $fields, ?string $feature = null, string $routeNamePrefix = '', ?CrudPermissions $permissions = null, bool $openPolicy = false, EntitySchemaMeta $schemaMeta = new EntitySchemaMeta, bool $apiEnvelope = false, string $nestedScopeSnippet = '', string $nestedExtraRelations = '', ?string $apiRouteAuth = null): array
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
        $owner = $permissions !== null && $permissions->owned ? new OwnedCrud($permissions) : null;
        $user = $owner === null ? [] : [OwnedCrud::userClass()];
        $controller = OwnedCrud::controllerValues($owner !== null, $names->plural);

        if (($schemaMeta->hasQueryFilters || $nestedScopeSnippet !== '') && $owner === null) {
            $controller['values']['indexParameters'] = "Request \$request, List{$names->plural} \$query";
            $controller['values']['listArguments'] = '$request';
            $controller['imports'] = [...$controller['imports'], 'Illuminate\\Http\\Request'];
        } elseif ($owner === null) {
            $controller['values']['indexParameters'] = "Request \$request, List{$names->plural} \$query";
            $controller['values']['listArguments'] = 'ListPerPage::fromRequest($request)';
            $controller['imports'] = [...$controller['imports'], 'Illuminate\\Http\\Request', 'Best\\Blueprint\\Support\\ListPerPage'];
        }

        $resourceFields = array_values(array_filter($fields, fn (CrudField $f): bool => ! $f->resourceHidden));
        $updateAbility = "'update', \$this->route('{$names->parameter}')";

        $modelImports = [
            $factory,
            'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
            'Illuminate\\Database\\Eloquent\\Model',
            ...($owner === null ? [] : OwnedCrud::modelImports()),
            ...$schemaMeta->modelImports,
        ];

        if ($schemaMeta->softDeletes) {
            $modelImports[] = 'Illuminate\\Database\\Eloquent\\SoftDeletes';
        }

        if ($schemaMeta->modelRelations !== '' || $nestedExtraRelations !== '') {
            $modelImports[] = 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo';
        }

        if ($schemaMeta->usesHasMany) {
            $modelImports[] = 'Illuminate\\Database\\Eloquent\\Relations\\HasMany';
        }

        if ($schemaMeta->modelScopes !== '') {
            $modelImports[] = 'Illuminate\\Database\\Eloquent\\Builder';
        }

        $enumImports = array_values(array_filter(array_map(
            fn (CrudField $f) => $f->import,
            array_filter($fields, fn (CrudField $f) => $f->needsEnumImport()),
        )));
        $needsRuleImport = $enumImports !== [] || array_reduce(
            $fields,
            fn (bool $carry, CrudField $f): bool => $carry || $f->columnUnique,
            false,
        );
        $requestImports = $needsRuleImport ? [...$enumImports, 'Illuminate\\Validation\\Rule'] : $enumImports;

        $files = [
            $paths['model'] => $render('model.stub', "{$ns}\\Models", $modelImports, [
                'fillable' => $this->lines($fields, fn (CrudField $f) => "'{$f->column}',", 8),
                'casts' => $this->casts($fields),
                'relations' => trim(($owner?->modelRelation() ?? '').$schemaMeta->modelRelations.$nestedExtraRelations),
                'modelScopes' => $schemaMeta->modelScopes,
                'softDeletesUse' => $schemaMeta->softDeletes ? "\n    use SoftDeletes;\n" : '',
                'modelPrimaryKeyBlock' => $schemaMeta->modelPrimaryKeyBlock,
            ]),
            $paths['migration'] => $stubs->render('migration.stub', $common + [
                'primaryKeyMigration' => $schemaMeta->primaryKeyMigrationLine === '' ? '' : $schemaMeta->primaryKeyMigrationLine."\n",
                'columns' => ($owner?->migrationColumn() ?? '').$this->lines($fields, fn (CrudField $f) => $f->migration().';', 12),
                'migrationExtras' => $this->migrationExtras($schemaMeta, $fields),
            ]),
            $paths['factory'] => $render('factory.stub', "Database\\Factories\\{$names->module}", [
                $model, 'Illuminate\\Database\\Eloquent\\Factories\\Factory', ...$date, ...$user,
            ], [
                'definition' => ($owner?->factoryLine() ?? '').$this->lines($fields, fn (CrudField $f) => "'{$f->column}' => {$f->factory()},", 12),
            ]),
            $paths['store'] => $render('request.stub', "{$ns}\\Requests", [
                $model, 'Illuminate\\Foundation\\Http\\FormRequest', ...$requestImports,
            ], [
                'class' => "Store{$e}Request",
                'ability' => "'create', {$e}::class",
                'rules' => $this->rules($fields, $names, false),
            ]),
            $paths['update'] => $owner === null
                ? $render('request.stub', "{$ns}\\Requests", [
                    'Illuminate\\Foundation\\Http\\FormRequest', ...$requestImports,
                ], ['class' => "Update{$e}Request", 'ability' => $updateAbility, 'rules' => $this->rules($fields, $names, true)])
                : $render('request-update-owned.stub', "{$ns}\\Requests", [
                    'Illuminate\\Auth\\Access\\Response', 'Illuminate\\Foundation\\Http\\FormRequest', 'Illuminate\\Support\\Facades\\Gate',
                ], ['class' => "Update{$e}Request", 'ability' => $updateAbility, 'rules' => $this->rules($fields, $names, true)]),
            $paths['data'] => $render('crud-data.stub', "{$ns}\\Data", [
                "{$ns}\\Requests\\Store{$e}Request", "{$ns}\\Requests\\Update{$e}Request", ...$date,
            ], [
                'properties' => $this->lines($fields, fn (CrudField $f) => "public {$f->type} \${$f->property},", 8),
                'arguments' => $this->lines($fields, fn (CrudField $f) => "{$f->property}: {$f->fromRequest()},", 12),
                'array' => $this->lines($fields, fn (CrudField $f) => "'{$f->column}' => \$this->{$f->property},", 12),
            ]),
            $paths['create'] => $render($owner === null ? 'crud-create-action.stub' : 'crud-create-action-owned.stub', "{$ns}\\Actions", [$data, $model, 'Illuminate\\Support\\Facades\\DB', ...$user]),
            $paths['updateAction'] => $render('crud-update-action.stub', "{$ns}\\Actions", [$data, $model, 'Illuminate\\Support\\Facades\\DB']),
            $paths['delete'] => $render('crud-delete-action.stub', "{$ns}\\Actions", [$model, 'Illuminate\\Support\\Facades\\DB']),
            $paths['query'] => $render(
                $owner === null
                    ? ($schemaMeta->hasQueryFilters || $nestedScopeSnippet !== '' ? 'query-with-filters.stub' : 'query.stub')
                    : 'query-owned.stub',
                "{$ns}\\Queries",
                [
                    $model,
                    'Illuminate\\Contracts\\Pagination\\LengthAwarePaginator',
                    'Best\\Blueprint\\Support\\ListPerPage',
                    ...($schemaMeta->hasQueryFilters || $nestedScopeSnippet !== '' ? ['Illuminate\\Http\\Request'] : []),
                    ...$user,
                ],
                [
                    'queryFilters' => $schemaMeta->queryFilterLines,
                    'listLatestColumn' => $schemaMeta->listLatestColumn,
                    'nestedScope' => $nestedScopeSnippet,
                ],
            ),
            $paths['controller'] => $render($apiEnvelope ? 'controller-api-envelope.stub' : 'controller.stub', "{$ns}\\Controllers", [
                "{$ns}\\Actions\\Create{$e}", "{$ns}\\Actions\\Delete{$e}", "{$ns}\\Actions\\Update{$e}",
                $data, $model, "{$ns}\\Queries\\List{$names->plural}",
                "{$ns}\\Requests\\Store{$e}Request", "{$ns}\\Requests\\Update{$e}Request",
                "{$ns}\\Resources\\{$e}Resource",
                'Illuminate\\Http\\JsonResponse', 'Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection',
                'Illuminate\\Http\\Response', 'Illuminate\\Support\\Facades\\Gate',
                ...($apiEnvelope ? ['Best\\Blueprint\\Http\\ApiResponse'] : []),
                ...$controller['imports'],
            ], $controller['values']),
            $paths['resource'] => $render('resource.stub', "{$ns}\\Resources", [
                $model, 'Illuminate\\Http\\Request', 'Illuminate\\Http\\Resources\\Json\\JsonResource',
            ], [
                'attributes' => $this->lines($resourceFields, fn (CrudField $f) => $f->kind() === 'date'
                    ? "'{$f->column}' => \$this->{$f->column}?->toIso8601String(),"
                    : "'{$f->column}' => \$this->{$f->column},", 12),
            ]),
            $paths['policy'] => $permissions === null
                ? $render(GeneratedPolicy::stub(false, false, $openPolicy), "{$ns}\\Policies", [$model, 'App\\Models\\User'])
                : $render(GeneratedPolicy::stub(true, $owner !== null, false), "{$ns}\\Policies", [
                    $model, 'App\\Models\\User', ...($owner === null ? [] : ['Illuminate\\Auth\\Access\\Response']),
                ], $permissions->policyValues()),
            $paths['test'] => $stubs->render($this->apiTestStub($names, $permissions, $openPolicy), $common + [
                'imports' => $this->imports([
                    ...($names->tenant
                        ? [$model, 'App\\Models\\User', 'Best\\Blueprint\\Testing\\InteractsWithTenancy', 'Illuminate\\Foundation\\Testing\\DatabaseMigrations']
                        : [$model, 'App\\Models\\User', 'Illuminate\\Foundation\\Testing\\RefreshDatabase']),
                    ...($feature === null ? [] : ['Best\\Blueprint\\Features\\FeatureAdmin']),
                    ...($permissions === null ? [] : ['Best\\Blueprint\\Testing\\InteractsWithPermissions']),
                ]),
                'validation' => $this->validationTest($names, $fields),
                'featureSetup' => $feature === null ? '' : "    app(FeatureAdmin::class)->enable('{$feature}');\n",
                'featureTest' => $feature === null ? '' : $this->featureTest($names, $feature),
                'factoryOwner' => OwnedCrud::factoryOwner($owner !== null),
                'apiSanctumSetup' => $apiRouteAuth === 'auth:sanctum' ? "    \$this->lebApiSanctum = true;\n" : '',
            ] + CrudPermissions::testValues($permissions, web: false)),
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
        $formInput = Str::camel($names->module).$e.'FormInput';

        $files[$paths['pageController']] = $render('web-controller.stub', "{$ns}\\Controllers", [
            "{$ns}\\Actions\\Create{$e}", "{$ns}\\Actions\\Delete{$e}", "{$ns}\\Actions\\Update{$e}",
            $data, $model, "{$ns}\\Queries\\List{$names->plural}",
            "{$ns}\\Requests\\Store{$e}Request", "{$ns}\\Requests\\Update{$e}Request",
            'Illuminate\\Contracts\\View\\View', 'Illuminate\\Http\\RedirectResponse', 'Illuminate\\Support\\Facades\\Gate', ...$controller['imports'],
        ], $web + $controller['values']);
        $files[$paths['viewIndex']] = $stubs->render('view-index.stub', $common + $web + [
            'headerCells' => $this->lines($fields, fn (CrudField $f) => "<th>{{ __('".Str::headline($f->column)."') }}</th>", 20),
            'rowCells' => $this->lines($fields, fn (CrudField $f) => '<td>'.$f->display($v).'</td>', 24),
        ]);
        $files[$paths['viewShow']] = $stubs->render('view-show.stub', $common + $web + [
            'details' => $this->lines($fields, fn (CrudField $f) => "<dt>{{ __('".Str::headline($f->column)."') }}</dt>\n            <dd>".$f->display($v).'</dd>', 12),
        ]);
        $files[$paths['viewCreate']] = $stubs->render('view-create.stub', $common + $web);
        $files[$paths['viewEdit']] = $stubs->render('view-edit.stub', $common + $web);
        $files[$paths['viewForm']] = $stubs->render('view-form.stub', [
            'inputs' => implode("\n\n", array_map(fn (CrudField $f) => $f->formInput($v), $fields)).($fields === [] ? '' : "\n"),
        ]);
        $files[$paths['webTest']] = $stubs->render($this->webTestStub($names, $permissions, $openPolicy), $common + [
            'imports' => $this->imports([
                ...($names->tenant
                    ? [$model, 'App\\Models\\User', 'Best\\Blueprint\\Testing\\InteractsWithTenancy', 'Illuminate\\Foundation\\Testing\\DatabaseMigrations']
                    : [$model, 'App\\Models\\User', 'Illuminate\\Foundation\\Testing\\RefreshDatabase']),
                ...($feature === null ? [] : ['Best\\Blueprint\\Features\\FeatureAdmin']),
                ...($permissions === null ? [] : ['Best\\Blueprint\\Testing\\InteractsWithPermissions']),
            ]),
            'url' => $names->tenant ? '$this->tenantUrl' : 'url',
            'formInput' => $formInput,
            'validation' => $this->webValidationTest($names, $fields),
            'featureSetup' => $feature === null ? '' : "    app(FeatureAdmin::class)->enable('{$feature}');\n",
            'featureTest' => $feature === null ? '' : $this->webFeatureTest($names, $feature),
            'factoryOwner' => OwnedCrud::factoryOwner($owner !== null),
        ] + CrudPermissions::testValues($permissions, web: true, formInput: $formInput));

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
    private function rules(array $fields, CrudNames $names, bool $forUpdate): string
    {
        $lines = '';

        foreach ($fields as $field) {
            foreach ([$field->column => $field->rules($forUpdate, $names->table, $names->parameter)] + $field->elementRules() as $key => $rules) {
                $formatted = array_map(fn (string $rule) => str_starts_with($rule, 'Rule::') ? $rule : "'{$rule}'", $rules);
                $lines .= str_repeat(' ', 12)."'{$key}' => [".implode(', ', $formatted)."],\n";
            }
        }

        return $lines;
    }

    /**
     * @param  list<CrudField>  $fields
     */
    private function casts(array $fields): string
    {
        $cast = array_values(array_filter($fields, fn (CrudField $f) => $f->castLine() !== null));

        if ($cast === []) {
            return '';
        }

        return "\n    /**\n     * @return array<string, string>\n     */\n    protected function casts(): array\n    {\n        return [\n"
            .$this->lines($cast, fn (CrudField $f) => $f->castLine().',', 12)
            ."        ];\n    }\n";
    }

    /**
     * @param  list<CrudField>  $fields
     */
    private function migrationExtras(EntitySchemaMeta $meta, array $fields): string
    {
        $lines = '';

        if ($meta->softDeletes) {
            $lines .= "            \$table->softDeletes();\n";
        }

        foreach ($meta->indexes as $columns) {
            $quoted = implode("', '", $columns);
            $lines .= "            \$table->index(['{$quoted}']);\n";
        }

        $columnUnique = array_map(
            fn (CrudField $f): ?string => $f->columnUnique ? $f->column : null,
            $fields,
        );
        $columnUnique = array_values(array_filter($columnUnique));

        foreach ($meta->unique as $columns) {
            if (count($columns) === 1 && in_array($columns[0], $columnUnique, true)) {
                continue;
            }

            $quoted = implode("', '", $columns);
            $lines .= "            \$table->unique(['{$quoted}']);\n";
        }

        if ($meta->compositePrimaryKeyColumns !== []) {
            $quoted = implode("', '", $meta->compositePrimaryKeyColumns);
            $lines .= "            \$table->primary(['{$quoted}']);\n";
        }

        return $lines;
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
            .'    $this->actAsApiUser($this->user)->postJson('.($names->tenant ? "\$this->tenantUrl('/api/{$names->uri}')" : "'/api/{$names->uri}'").", [])\n"
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
            ."    \$this->actAsApiUser(\$this->user)->getJson({$url})->assertForbidden();\n"
            ."});\n";
    }

    private function featureError(string $feature): ?string
    {
        $profile = Profile::current();

        if ($profile === null || ! $profile->usesFeatures()) {
            return '--feature needs the saas or tenant-saas profile.';
        }

        $class = Definitions::features();

        if (! class_exists($class)) {
            return 'Feature definitions not found; run php artisan engineering:install.';
        }

        return array_key_exists($feature, $class::defaults()) ? null : "Unknown feature \"{$feature}\". Add it to {$class} first.";
    }

    /**
     * "tenant" or "central" in tenant-saas (from the route groups its Provider uses), "plain" for a
     * tenant-saas module made before the switch, "none" in other profiles.
     */
    private function moduleKind(string $provider): string
    {
        if (Profile::current() !== Profile::TenantSaas) {
            return 'none';
        }

        $code = is_file($provider) ? (string) file_get_contents($provider) : '';

        return match (true) {
            str_contains($code, "'central'") => 'central',
            str_contains($code, "'tenant'") => 'tenant',
            str_contains($code, 'Route::') => 'plain',
            default => 'tenant', // no routes yet: routes added by make:module in tenant-saas are tenant routes
        };
    }

    /**
     * The fields of an existing CRUD, read back from its Data class (name:type list).
     */
    private function existingFields(CrudNames $names): string
    {
        $code = (string) file_get_contents(base_path($names->files()['data']));
        preg_match('/function __construct\((.*?)\)\s*\{\}/s', $code, $constructor);
        preg_match_all('/public (\??)([\w\\\\]+) \$(\w+),/', $constructor[1] ?? '', $properties, PREG_SET_ORDER);

        return implode(',', array_map(
            fn (array $p) => $p[3].':'.$p[1].($p[2] === 'CarbonImmutable' ? 'Carbon\\CarbonImmutable' : $p[2]),
            $properties,
        ));
    }

    /**
     * @param  list<CrudField>  $fields
     */
    private function fieldSignature(array $fields): string
    {
        return implode(',', array_map(fn (CrudField $f) => $f->property.':'.$f->type, $fields));
    }

    /**
     * When adding a side, it must be gated by the same feature as the existing side's routes.
     */
    private function featureMismatch(CrudNames $names, ?string $feature, bool $api, bool $web): ?string
    {
        foreach (array_filter(['api' => ! $api ? "{$names->entity}Controller" : null, 'web' => ! $web ? "{$names->entity}PageController" : null]) as $side => $controller) {
            $routes = base_path("{$names->modulePath}/routes/{$side}.php");

            if (! is_file($routes)) {
                continue;
            }

            preg_match('/\b'.$controller.'::class\)[^;]*?feature:([\w.-]+)/s', (string) file_get_contents($routes), $match);
            $existing = $match[1] ?? null;

            if ($existing !== null && $existing !== $feature) {
                return "The existing routes are gated by feature:{$existing}; add --feature={$existing} so the new side is gated too.";
            }

            if ($existing === null && $feature !== null) {
                return "The existing routes have no feature gate; gate them with feature:{$feature} first, or leave --feature out.";
            }
        }

        return null;
    }

    /**
     * When adding a side, it must use permissions exactly when the existing CRUD's policy does.
     */
    private function permissionsMismatch(CrudNames $names, bool $permissions, bool $owned): ?string
    {
        $policy = $this->existingFile($names, 'policy');
        $source = $policy === null ? '' : (string) file_get_contents(base_path($policy));
        $existing = CrudPermissions::usedBy($source);
        $existingOwned = $existing && OwnedCrud::usedBy($source);

        return match (true) {
            $existing && ! $permissions => "The existing {$names->entity} CRUD uses permissions; add --permissions".($existingOwned ? ' --owned.' : '.'),
            ! $existing && $permissions => "The existing {$names->entity} CRUD does not use permissions; leave --permissions out.",
            $existingOwned && ! $owned => "The existing {$names->entity} CRUD is owned; add --owned.",
            ! $existingOwned && $owned => "The existing {$names->entity} CRUD is not owned; leave --owned out.",
            default => null,
        };
    }

    private function apiTestStub(CrudNames $names, ?CrudPermissions $permissions, bool $openPolicy): string
    {
        if ($permissions !== null || $openPolicy) {
            return $names->tenant ? 'test-tenant.stub' : 'test.stub';
        }

        return $names->tenant ? 'test-tenant-deny.stub' : 'test-deny.stub';
    }

    private function webTestStub(CrudNames $names, ?CrudPermissions $permissions, bool $openPolicy): string
    {
        if ($permissions !== null || $openPolicy) {
            return $names->tenant ? 'test-web-tenant.stub' : 'test-web.stub';
        }

        return $names->tenant ? 'test-web-tenant-deny.stub' : 'test-web-deny.stub';
    }

    /**
     * When adding a side, the new run must use the same policy mode as the existing CRUD.
     */
    private function openPolicyMismatch(CrudNames $names, bool $openPolicy, bool $permissions): ?string
    {
        if ($permissions) {
            return null;
        }

        $policy = $this->existingFile($names, 'policy');

        if ($policy === null) {
            return null;
        }

        $source = (string) file_get_contents(base_path($policy));

        if (GeneratedPolicy::isPermissionsMode($source)) {
            return null;
        }

        $wasOpen = GeneratedPolicy::isOpen($source);
        $wasDeny = GeneratedPolicy::isDenyByDefault($source);

        if ($wasOpen && ! $openPolicy) {
            return "The existing {$names->entity} CRUD uses an open policy; add --open-policy or edit {$names->entity}Policy.";
        }

        if ($wasDeny && $openPolicy) {
            return "The existing {$names->entity} CRUD uses a deny-by-default policy; leave --open-policy out.";
        }

        // Legacy policies (return true, no marker): treat as open.
        if (! $wasOpen && ! $wasDeny && $openPolicy === false) {
            return "The existing {$names->entity}Policy looks like a legacy open policy; add --open-policy when adding a side, or replace the policy with a deny-by-default version.";
        }

        return null;
    }

    /**
     * The definitions file with the entity's group added (edited is null when the group is already there; missing
     * lists the abilities an existing group lacks).
     *
     * @return array{0: array{path: string, relative: string, original: string, edited: ?string, missing: list<string>}|null, 1: ?string}
     */
    private function definitionsEdit(Filesystem $files, CrudPermissions $permissions): array
    {
        $class = $permissions->definitionsClass();
        $path = (string) (new ReflectionClass($class))->getFileName();
        $relative = str_starts_with($path, base_path().'/') ? substr($path, strlen(base_path()) + 1) : $path;
        $entry = DefinitionsFile::entry($permissions->group(), $permissions->abilities());
        $manual = "Add {$entry} to it, then re-run.";

        if (! $files->isFile($path) || ! is_writable($path)) {
            return [null, "Cannot edit the permission definitions in {$relative}. {$manual}"];
        }

        $original = $files->get($path);

        try {
            $exists = DefinitionsFile::hasGroup($original, $permissions->group());
            $edited = $exists ? null : DefinitionsFile::addGroup($original, $permissions->group(), $permissions->abilities());
            // Unknown when the existing value isn't a plain list of strings: no warning then.
            $missing = $exists ? array_values(array_diff($permissions->abilities(), DefinitionsFile::groupAbilities($original, $permissions->group()) ?? $permissions->abilities())) : [];
        } catch (InvalidPermissionDefinition) {
            return [null, "Could not find the array returned by groups() in {$relative}. {$manual}"];
        }

        return [['path' => $path, 'relative' => $relative, 'original' => $original, 'edited' => $edited, 'missing' => $missing], null];
    }

    /**
     * A requested permissions mode must never be dropped by an old published stub: the policy must check
     * permissions and the generated tests must grant them.
     *
     * @param  array<string, string>  $generated
     */
    private function permissionsStubError(StubRenderer $stubs, array $generated, CrudNames $names, bool $api, bool $web, bool $owned): ?string
    {
        $policy = $generated[$names->files()['policy']] ?? null;

        if ($policy !== null && ! CrudPermissions::usedBy($policy)) {
            return "Stub {$stubs->path(GeneratedPolicy::stub(true, $owned, false))} did not produce a valid policy (no checkPermissionTo). Update or delete it, then re-run.";
        }

        foreach (array_keys(array_filter(['test' => $api, 'webTest' => $web])) as $key) {
            if (! CrudPermissions::testsSetUp($generated[$names->files()[$key]])) {
                $stub = $stubs->path(match ($key) {
                    'test' => $names->tenant ? 'test-tenant.stub' : 'test.stub',
                    default => $names->tenant ? 'test-web-tenant.stub' : 'test-web.stub',
                });

                return "Stub {$stub} did not produce valid tests (no permission setup). Update or delete it, then re-run.";
            }
        }

        return null;
    }

    /**
     * @return string|null|false middleware name, null when none, false when option invalid
     */
    private function resolvedRouteAuth(bool $generatingApi): null|string|false
    {
        if (! $generatingApi) {
            return null;
        }

        $option = $this->option('auth');

        if ($option !== null && $option !== false && $option !== '') {
            $normalized = RouteAuth::normalize((string) $option);

            return $normalized ?? false;
        }

        return RouteAuth::fromConfig();
    }

    private function resolvedWebRouteAuth(bool $generatingWeb): null|string|false
    {
        if (! $generatingWeb) {
            return null;
        }

        $option = $this->option('auth');

        if ($option !== null && $option !== false && $option !== '') {
            $value = trim((string) $option);

            if (in_array($value, ['sanctum', 'auth:sanctum'], true)) {
                return null;
            }

            return RouteAuth::normalizeWeb($value) ?? false;
        }

        return RouteAuth::webFromConfig();
    }

    /**
     * @param  list<CrudField>  $fields
     * @return null|array{fields: list<CrudField>, parent: CrudNames, scope: string, relations: string}|string
     */
    private function prepareNestedUnder(
        Modules $modules,
        string $module,
        string $entity,
        CrudNames $names,
        bool $tenant,
        bool $api,
        bool $web,
        array $fields,
    ): null|array|string {
        $parentInput = $this->option('nested-under');

        if ($parentInput === null || $parentInput === false || $parentInput === '') {
            return null;
        }

        if (! $api) {
            return '--nested-under requires --api.';
        }

        if ($web) {
            return '--nested-under cannot be combined with --web.';
        }

        $parentEntity = ModuleName::normalize((string) $parentInput);

        if ($parentEntity === null || PhpNames::isReserved($parentEntity)) {
            return "Invalid parent entity \"{$parentInput}\".";
        }

        $parentNames = CrudNames::for(
            $parentEntity,
            $module,
            $modules->namespace().'\\'.$module,
            $modules->path().'/'.$module,
            $tenant,
        );

        $parentModelPath = base_path("{$parentNames->modulePath}/Models/{$parentEntity}.php");

        if (! is_file($parentModelPath)) {
            return "Parent model not found at {$parentNames->modulePath}/Models/{$parentEntity}.php — generate parent CRUD first.";
        }

        $fkColumn = Str::snake($parentEntity).'_id';
        $hasFk = false;

        foreach ($fields as $field) {
            if ($field->column === $fkColumn) {
                $hasFk = true;

                break;
            }
        }

        if (! $hasFk) {
            $parentModel = "{$modules->namespace()}\\{$module}\\Models\\{$parentEntity}";
            $fields[] = new CrudField(
                property: Str::camel($fkColumn),
                column: $fkColumn,
                type: 'int',
                nullable: false,
                import: null,
                extendedKind: 'foreignId',
                referencesTable: $parentNames->table,
                onDelete: 'cascade',
                relatedModelFqn: $parentModel,
                relatedModelShort: $parentEntity,
            );
        }

        $relationMethod = Str::camel($parentEntity);
        $parentModel = "{$modules->namespace()}\\{$module}\\Models\\{$parentEntity}";
        $relations = "\n\n    public function {$relationMethod}(): BelongsTo\n    {\n        return \$this->belongsTo({$parentEntity}::class);\n    }\n";

        $scope = "\n        \$query->where('{$fkColumn}', \$request->route('{$parentNames->parameter}'));";

        return [
            'fields' => $fields,
            'parent' => $parentNames,
            'scope' => $scope,
            'relations' => $relations,
        ];
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
