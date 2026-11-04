<?php

use App\Modules\Orders\Data\ServiceCheckData;
use App\Modules\Orders\Services\RunServiceCheck;
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

function serviceFile(string $class = 'OrderPricing'): string
{
    return file_get_contents(app_path("Modules/Orders/Services/{$class}.php"));
}

function serviceHeader(string $imports, string $class = 'OrderPricing'): string
{
    return "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Modules\\Orders\\Services;\n\n{$imports}final class {$class}\n{\n";
}

it('generates exact output', function (array $options, string $expected) {
    $this->artisan('make:service', ['module' => 'Orders', 'name' => 'OrderPricing'] + $options)
        ->expectsOutputToContain('created app/Modules/Orders/Services/OrderPricing.php')
        ->assertExitCode(0);

    expect(serviceFile())->toBe($expected);
})->with([
    'no flags' => [[], serviceHeader('')
        ."    public function handle(): void\n    {\n        // ...\n    }\n}\n"],
    'data only' => [['--data' => 'OrderData'], serviceHeader("use App\\Modules\\Orders\\Data\\OrderData;\n\n")
        ."    public function handle(OrderData \$data): void\n    {\n        // ...\n    }\n}\n"],
]);

it('refuses missing data class', function () {
    $this->artisan('make:service', ['module' => 'Orders', 'name' => 'OrderPricing', '--data' => 'Missing'])
        ->expectsOutputToContain('Data class "Missing" not found in app/Modules/Orders/Data. Create it with php artisan make:dto Orders Missing.')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Services')))->toBeFalse();
});

it('refuses existing class', function () {
    $this->artisan('make:service', ['module' => 'Orders', 'name' => 'OrderPricing'])->assertExitCode(0);

    $this->artisan('make:service', ['module' => 'Orders', 'name' => 'orderPricing'])
        ->expectsOutputToContain('"app/Modules/Orders/Services/OrderPricing.php" already exists. Nothing was changed.')
        ->assertExitCode(1);
});

it('shows naming tip', function () {
    $this->artisan('make:service', ['module' => 'Orders', 'name' => 'OrderPricingService'])
        ->expectsOutputToContain('Tip: the Services folder already says it is a service; consider "OrderPricing".')
        ->assertExitCode(0);
});

it('generated service works', function () {
    $this->artisan('make:dto', ['module' => 'Orders', 'name' => 'ServiceCheckData', '--fields' => 'name:string'])->assertExitCode(0);
    $this->artisan('make:service', ['module' => 'Orders', 'name' => 'RunServiceCheck', '--data' => 'ServiceCheckData'])->assertExitCode(0);

    foreach (['Data/ServiceCheckData.php', 'Services/RunServiceCheck.php'] as $file) {
        $lint = new Process([PHP_BINARY, '-l', app_path('Modules/Orders/'.$file)]);
        $lint->run();
        expect($lint->getExitCode())->toBe(0);
        require_once app_path('Modules/Orders/'.$file);
    }

    $data = new ServiceCheckData('a');

    expect((new RunServiceCheck)->handle($data))->toBeNull();
});
