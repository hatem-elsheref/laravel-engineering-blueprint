<?php

use App\Modules\Orders\Actions\RunActionCheck;
use App\Modules\Orders\Data\ActionCheckData;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

beforeEach(function () {
    config()->set('blueprint.profile', 'modular');
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    $this->artisan('make:module', ['name' => 'Orders'])->assertExitCode(0);
    $this->artisan('make:dto', ['module' => 'Orders', 'name' => 'OrderData', '--fields' => 'name:string'])->assertExitCode(0);
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::deleteDirectory(base_path('stubs'));
    file_put_contents($this->providersPath, $this->providers);
});

function actionFile(string $class = 'CreateOrder'): string
{
    return file_get_contents(app_path("Modules/Orders/Actions/{$class}.php"));
}

function actionHeader(string $imports): string
{
    return "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Modules\\Orders\\Actions;\n\n{$imports}final class CreateOrder\n{\n";
}

it('generates exact output', function (array $options, string $expected) {
    $this->artisan('make:action', ['module' => 'Orders', 'name' => 'CreateOrder'] + $options)
        ->expectsOutputToContain('created app/Modules/Orders/Actions/CreateOrder.php')
        ->assertExitCode(0);

    expect(actionFile())->toBe($expected);
})->with([
    'spec example' => [['--data' => 'OrderData', '--transaction' => true], actionHeader("use App\\Modules\\Orders\\Data\\OrderData;\nuse Illuminate\\Support\\Facades\\DB;\n\n")
        ."    public function handle(OrderData \$data): void\n    {\n        DB::transaction(function () use (\$data): void {\n            // ...\n        });\n    }\n}\n"],
    'no flags' => [[], actionHeader('')
        ."    public function handle(): void\n    {\n        // ...\n    }\n}\n"],
    'data only' => [['--data' => 'OrderData'], actionHeader("use App\\Modules\\Orders\\Data\\OrderData;\n\n")
        ."    public function handle(OrderData \$data): void\n    {\n        // ...\n    }\n}\n"],
    'transaction only' => [['--transaction' => true], actionHeader("use Illuminate\\Support\\Facades\\DB;\n\n")
        ."    public function handle(): void\n    {\n        DB::transaction(function (): void {\n            // ...\n        });\n    }\n}\n"],
]);

it('refuses missing data class', function () {
    $this->artisan('make:action', ['module' => 'Orders', 'name' => 'CreateOrder', '--data' => 'Missing'])
        ->expectsOutputToContain('Data class "Missing" not found in app/Modules/Orders/Data. Create it with php artisan make:dto Orders Missing.')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Actions')))->toBeFalse();
});

it('matches data class case-insensitively', function () {
    $this->artisan('make:action', ['module' => 'Orders', 'name' => 'CreateOrder', '--data' => 'orderdata'])->assertExitCode(0);

    expect(actionFile())->toContain('use App\Modules\Orders\Data\OrderData;')->toContain('handle(OrderData $data)');
});

it('empty data option means none', function () {
    $this->artisan('make:action', ['module' => 'Orders', 'name' => 'CreateOrder', '--data' => ''])->assertExitCode(0);

    expect(actionFile())->toContain('public function handle(): void');
});

it('refuses existing class', function () {
    $this->artisan('make:action', ['module' => 'Orders', 'name' => 'CreateOrder'])->assertExitCode(0);

    $this->artisan('make:action', ['module' => 'Orders', 'name' => 'createOrder'])
        ->expectsOutputToContain('"CreateOrder" already exists at app/Modules/Orders/Actions/CreateOrder.php.')
        ->assertExitCode(1);
});

it('refuses missing module', function () {
    $this->artisan('make:action', ['module' => 'Billing', 'name' => 'CreateInvoice'])
        ->expectsOutputToContain('Module "Billing" not found in app/Modules.')
        ->assertExitCode(1);
});

it('refuses profile and path problems', function (string $key, string $value) {
    config()->set($key, $value);

    $this->artisan('make:action', ['module' => 'Orders', 'name' => 'CreateOrder'])->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Actions')))->toBeFalse();
})->with([
    ['blueprint.profile', 'basic'],
    ['blueprint.module_path', 'src/Modules'],
]);

it('shows naming tip', function () {
    $this->artisan('make:action', ['module' => 'Orders', 'name' => 'CreateOrderAction'])
        ->expectsOutputToContain('Tip: the Actions folder already says it is an action; consider "CreateOrder".')
        ->assertExitCode(0);
});

it('refuses old published stub', function () {
    File::ensureDirectoryExists(base_path('stubs/blueprint'));
    file_put_contents(base_path('stubs/blueprint/action.stub'), <<<'STUB'
<?php

namespace App\Modules\{{ moduleNamespace }}\Actions;

use App\Modules\{{ moduleNamespace }}\Data\{{ actionData }};

final class {{ actionClass }}
{
}
STUB);

    $this->artisan('make:action', ['module' => 'Orders', 'name' => 'CreateOrder'])
        ->expectsOutputToContain('did not produce a valid class (unknown placeholder {{ moduleNamespace }})')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Actions')))->toBeFalse();
});

it('generated action works', function () {
    $this->artisan('make:dto', ['module' => 'Orders', 'name' => 'ActionCheckData', '--fields' => 'name:string'])->assertExitCode(0);
    $this->artisan('make:action', ['module' => 'Orders', 'name' => 'RunActionCheck', '--data' => 'ActionCheckData', '--transaction' => true])->assertExitCode(0);

    foreach (['Data/ActionCheckData.php', 'Actions/RunActionCheck.php'] as $file) {
        $lint = new Process([PHP_BINARY, '-l', app_path('Modules/Orders/'.$file)]);
        $lint->run();
        expect($lint->getExitCode())->toBe(0);
        require_once app_path('Modules/Orders/'.$file);
    }

    $data = new ActionCheckData('a');

    expect((new RunActionCheck)->handle($data))->toBeNull();
});

it('refuses reserved class names', function (string $name) {
    $this->artisan('make:action', ['module' => 'Orders', 'name' => $name])
        ->expectsOutputToContain("Invalid class name \"{$name}\".")
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Actions')))->toBeFalse();
})->with(['Void', 'Print', 'Clone']);

it('names the data class it looked for', function () {
    $this->artisan('make:action', ['module' => 'Orders', 'name' => 'CreateOrder', '--data' => 'missing_data'])
        ->expectsOutputToContain('Data class "MissingData" not found in app/Modules/Orders/Data. Create it with php artisan make:dto Orders MissingData.')
        ->assertExitCode(1);
});

it('refuses data classes from other modules', function () {
    $this->artisan('make:action', ['module' => 'Orders', 'name' => 'CreateOrder', '--data' => 'Billing\InvoiceData'])
        ->expectsOutputToContain('Data classes must be in this module (got "Billing\InvoiceData"). Pass a class name from app/Modules/Orders/Data.')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Actions')))->toBeFalse();
});
