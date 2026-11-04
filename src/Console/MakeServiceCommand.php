<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Support\ModuleName;
use Best\Blueprint\Support\Modules;

class MakeServiceCommand extends ModuleClassCommand
{
    protected $signature = 'make:service
        {module : Module name, e.g. Orders}
        {name : Class name, e.g. OrderPricing}
        {--data= : Data class in this module, e.g. OrderData}';

    protected $description = 'Create a Service (shared business capability) in a module';

    protected function folder(): string
    {
        return 'Services';
    }

    protected function stub(): string
    {
        return 'service.stub';
    }

    protected function example(): string
    {
        return 'OrderPricing';
    }

    protected function values(string $module, string $moduleNamespace, string $class): array|string
    {
        $imports = [];
        $data = null;
        $dataInput = trim((string) $this->option('data'));

        if ($dataInput !== '') {
            $dataFolder = (new Modules)->path()."/{$module}/Data";

            if (str_contains($dataInput, '\\') || str_contains($dataInput, '/')) {
                return "Data classes must be in this module (got \"{$dataInput}\"). Pass a class name from {$dataFolder}.";
            }

            $dataName = ModuleName::normalize($dataInput);

            if ($dataName === null) {
                return "Invalid data class name \"{$dataInput}\". Use letters and numbers, starting with a letter, e.g. OrderData.";
            }

            $file = Modules::entry(base_path($dataFolder), $dataName.'.php');

            if ($file === null) {
                return "Data class \"{$dataName}\" not found in {$dataFolder}. Create it with php artisan make:dto {$module} {$dataName}.";
            }

            $data = pathinfo($file, PATHINFO_FILENAME);
            $imports[] = "{$moduleNamespace}\\Data\\{$data}";
        }

        return [
            'imports' => $this->imports($imports),
            'parameters' => $data === null ? '' : "{$data} \$data",
            'body' => "        // ...\n",
        ];
    }

    protected function tip(string $class): ?string
    {
        if (! str_ends_with($class, 'Service') || $class === 'Service') {
            return null;
        }

        return 'Tip: the Services folder already says it is a service; consider "'.substr($class, 0, -strlen('Service')).'".';
    }
}
