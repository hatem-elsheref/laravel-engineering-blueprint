<?php

namespace Best\Blueprint\Support;

use Illuminate\Support\Str;

/**
 * Every name and path make:crud derives from a module and a singular entity.
 */
final readonly class CrudNames
{
    /** Files both sides use. */
    public const SHARED = ['model', 'migration', 'factory', 'store', 'update', 'data', 'create', 'updateAction', 'delete', 'query', 'policy'];

    /** JSON API side. */
    public const API = ['controller', 'resource', 'test'];

    /** Blade (web) side. */
    public const WEB = ['pageController', 'viewIndex', 'viewCreate', 'viewEdit', 'viewShow', 'viewForm', 'webTest'];

    private function __construct(
        public string $entity,
        public string $plural,
        public string $table,
        public string $uri,
        public string $variable,
        public string $parameter,
        public string $module,
        public string $moduleNamespace,
        public string $modulePath,
        public string $timestamp,
        public bool $tenant = false,
    ) {}

    public static function for(string $entity, string $module, string $moduleNamespace, string $modulePath, bool $tenant = false): self
    {
        $plural = Str::pluralStudly($entity);

        return new self(
            entity: $entity,
            plural: $plural,
            table: Str::snake($plural),
            uri: Str::kebab($plural),
            variable: Str::camel($entity),
            parameter: Str::snake($entity),
            module: $module,
            moduleNamespace: $moduleNamespace,
            modulePath: $modulePath,
            timestamp: date('Y_m_d_His'),
            tenant: $tenant,
        );
    }

    /**
     * Base-relative path of every generated file, keyed by role.
     *
     * @return array<string, string>
     */
    public function files(): array
    {
        $m = $this->modulePath;
        $e = $this->entity;

        return [
            'model' => "{$m}/Models/{$e}.php",
            'migration' => 'database/migrations/'.($this->tenant ? 'tenant/' : '')."{$this->timestamp}_create_{$this->table}_table.php",
            'factory' => "database/factories/{$this->module}/{$e}Factory.php",
            'store' => "{$m}/Requests/Store{$e}Request.php",
            'update' => "{$m}/Requests/Update{$e}Request.php",
            'data' => "{$m}/Data/{$e}Data.php",
            'create' => "{$m}/Actions/Create{$e}.php",
            'updateAction' => "{$m}/Actions/Update{$e}.php",
            'delete' => "{$m}/Actions/Delete{$e}.php",
            'query' => "{$m}/Queries/List{$this->plural}.php",
            'controller' => "{$m}/Controllers/{$e}Controller.php",
            'resource' => "{$m}/Resources/{$e}Resource.php",
            'policy' => "{$m}/Policies/{$e}Policy.php",
            'test' => "tests/Feature/Modules/{$this->module}/{$e}ApiTest.php",
            'pageController' => "{$m}/Controllers/{$e}PageController.php",
            'viewIndex' => "resources/views/{$this->viewFolder()}/index.blade.php",
            'viewCreate' => "resources/views/{$this->viewFolder()}/create.blade.php",
            'viewEdit' => "resources/views/{$this->viewFolder()}/edit.blade.php",
            'viewShow' => "resources/views/{$this->viewFolder()}/show.blade.php",
            'viewForm' => "resources/views/{$this->viewFolder()}/_form.blade.php",
            'webTest' => "tests/Feature/Modules/{$this->module}/{$e}WebTest.php",
        ];
    }

    /** View name prefix, e.g. "orders.orders" (module/entity plural, kebab-case). */
    public function viewPath(): string
    {
        return Str::kebab($this->module).'.'.$this->uri;
    }

    private function viewFolder(): string
    {
        return Str::kebab($this->module).'/'.$this->uri;
    }
}
