<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Support\FieldException;
use Best\Blueprint\Support\Fields;

class MakeDtoCommand extends ModuleClassCommand
{
    protected $signature = 'make:dto
        {module : Module name, e.g. Orders}
        {name : Class name, e.g. OrderData}
        {--fields= : Comma-separated name:type list, e.g. "name:string,quantity:int,note:?string"}';

    protected $description = 'Create a Data class (typed, immutable) in a module';

    protected function folder(): string
    {
        return 'Data';
    }

    protected function stub(): string
    {
        return 'dto.stub';
    }

    protected function example(): string
    {
        return 'OrderData';
    }

    protected function values(string $module, string $moduleNamespace, string $class): array|string
    {
        try {
            $fields = Fields::parse($this->option('fields'));
        } catch (FieldException $e) {
            return $e->getMessage();
        }

        $properties = $fields === []
            ? "        // public string \$name,\n"
            : implode('', array_map(fn (array $f) => "        public {$f['type']} \${$f['name']},\n", $fields));

        return [
            'imports' => $this->imports(array_values(array_filter(array_column($fields, 'import')))),
            'properties' => $properties,
        ];
    }

    protected function tip(string $class): ?string
    {
        return str_ends_with($class, 'Data')
            ? null
            : "Tip: data classes are usually named like {$class}Data.";
    }
}
