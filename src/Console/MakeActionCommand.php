<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Support\ModuleName;
use Best\Blueprint\Support\Modules;

class MakeActionCommand extends ModuleClassCommand
{
    protected $signature = 'make:action
        {module : Module name, e.g. Orders}
        {name : Class name, e.g. CreateOrder}
        {--data= : Data class in this module, e.g. OrderData}
        {--transaction : Wrap handle() in DB::transaction}';

    protected $description = 'Create an Action (one business operation) in a module';

    protected function folder(): string
    {
        return 'Actions';
    }

    protected function stub(): string
    {
        return 'action.stub';
    }

    protected function example(): string
    {
        return 'CreateOrder';
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

        $uses = $data === null ? '' : ' use ($data)';
        $body = '        // ...'."\n";

        if ($this->option('transaction')) {
            $imports[] = 'Illuminate\\Support\\Facades\\DB';
            $body = "        DB::transaction(function (){$uses}: void {\n"
                ."            // ...\n"
                ."        });\n";
        }

        return [
            'imports' => $this->imports($imports),
            'parameters' => $data === null ? '' : "{$data} \$data",
            'body' => $body,
        ];
    }

    protected function tip(string $class): ?string
    {
        if (! str_ends_with($class, 'Action') || $class === 'Action') {
            return null;
        }

        return 'Tip: the Actions folder already says it is an action; consider "'.substr($class, 0, -strlen('Action')).'".';
    }
}
