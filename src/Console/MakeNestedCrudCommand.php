<?php

declare(strict_types=1);

namespace Best\Blueprint\Console;

use Illuminate\Console\Command;

final class MakeNestedCrudCommand extends Command
{
    protected $signature = 'make:nested-crud
        {module : Module name, e.g. Orders}
        {parent : Parent entity, e.g. Order}
        {entity : Child entity, e.g. OrderLine}
        {--fields= : Comma-separated fields (parent foreign key is added automatically)}
        {--schema= : Child entity schema YAML}
        {--permissions : Named permission policy}
        {--open-policy : Demo policy}
        {--auth= : Same as make:crud --auth}
        {--api-envelope : Same as make:crud --api-envelope}';

    protected $description = 'Create a nested JSON API CRUD under a parent entity (see docs/NESTED-RESOURCES.md)';

    public function handle(): int
    {
        $arguments = [
            'module' => (string) $this->argument('module'),
            'entity' => (string) $this->argument('entity'),
            '--api' => true,
            '--nested-under' => (string) $this->argument('parent'),
        ];

        foreach (['fields', 'schema', 'auth'] as $option) {
            if ($this->option($option) !== null) {
                $arguments['--'.$option] = $this->option($option);
            }
        }

        foreach (['permissions', 'open-policy', 'api-envelope'] as $flag) {
            if ($this->option($flag)) {
                $arguments['--'.$flag] = true;
            }
        }

        return $this->call('make:crud', $arguments);
    }
}
