<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    config()->set('blueprint.profile', 'modular');
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    $this->artisan('make:module', ['name' => 'Orders'])->assertExitCode(0);
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::deleteDirectory(base_path('stubs'));
    file_put_contents($this->providersPath, $this->providers);
});

function dtoFile(): string
{
    return file_get_contents(app_path('Modules/Orders/Data/OrderData.php'));
}

const DTO_EXAMPLE = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Orders\Data;

use Carbon\CarbonImmutable;

final readonly class OrderData
{
    public function __construct(
        public string $name,
        public int $quantity,
        public ?string $note,
        public CarbonImmutable $placedAt,
    ) {}
}

PHP;

const DTO_FIELDS = 'name:string,quantity:int,note:?string,placedAt:Carbon\CarbonImmutable';

it('generates the spec example', function () {
    $this->artisan('make:dto', ['module' => 'Orders', 'name' => 'OrderData', '--fields' => DTO_FIELDS])
        ->expectsOutputToContain('created app/Modules/Orders/Data/OrderData.php')
        ->assertExitCode(0);

    expect(dtoFile())->toBe(DTO_EXAMPLE);
});

it('generates without imports', function () {
    $this->artisan('make:dto', ['module' => 'Orders', 'name' => 'OrderData', '--fields' => 'name:string'])->assertExitCode(0);

    expect(dtoFile())->toBe(<<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Orders\Data;

final readonly class OrderData
{
    public function __construct(
        public string $name,
    ) {}
}

PHP);
});

it('generates an empty constructor', function () {
    $this->artisan('make:dto', ['module' => 'Orders', 'name' => 'OrderData'])->assertExitCode(0);

    expect(dtoFile())->toContain("    public function __construct(\n        // public string \$name,\n    ) {}");
});

it('refuses invalid fields', function () {
    $this->artisan('make:dto', ['module' => 'Orders', 'name' => 'OrderData', '--fields' => 'name:text'])
        ->expectsOutputToContain('Invalid field "name:text": unknown type text.')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Data')))->toBeFalse();
});

it('resolves module case-insensitively', function () {
    $this->artisan('make:dto', ['module' => 'orders', 'name' => 'OrderData'])->assertExitCode(0);

    expect(file_exists(app_path('Modules/Orders/Data/OrderData.php')))->toBeTrue();
});

it('refuses missing module', function () {
    $this->artisan('make:dto', ['module' => 'Billing', 'name' => 'InvoiceData'])
        ->expectsOutputToContain('Module "Billing" not found in app/Modules. Create it with php artisan make:module Billing.')
        ->assertExitCode(1);
});

it('refuses existing class', function (string $again) {
    $this->artisan('make:dto', ['module' => 'Orders', 'name' => 'OrderData', '--fields' => 'name:string'])->assertExitCode(0);
    $before = dtoFile();

    $this->artisan('make:dto', ['module' => 'Orders', 'name' => $again])
        ->expectsOutputToContain('"OrderData" already exists at app/Modules/Orders/Data/OrderData.php.')
        ->assertExitCode(1);

    expect(dtoFile())->toBe($before);
})->with(['OrderData', 'orderdata']);

it('normalizes and validates class name', function () {
    $this->artisan('make:dto', ['module' => 'Orders', 'name' => 'order_data'])
        ->expectsOutputToContain('Using class name "OrderData".')
        ->assertExitCode(0);

    $this->artisan('make:dto', ['module' => 'Orders', 'name' => '1x'])
        ->expectsOutputToContain('Invalid class name "1x". Use letters and numbers, starting with a letter, e.g. OrderData.')
        ->assertExitCode(1);
});

it('shows naming tip', function () {
    $this->artisan('make:dto', ['module' => 'Orders', 'name' => 'Order'])
        ->expectsOutputToContain('Tip: data classes are usually named like OrderData.')
        ->assertExitCode(0);
});

it('name equal to suffix', function () {
    $this->artisan('make:dto', ['module' => 'Orders', 'name' => 'Data'])
        ->doesntExpectOutputToContain('Tip:')
        ->assertExitCode(0);
});

it('refuses profile and path problems', function (string $key, string $value) {
    config()->set($key, $value);

    $this->artisan('make:dto', ['module' => 'Orders', 'name' => 'OrderData'])->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Data')))->toBeFalse();
})->with([
    ['blueprint.profile', 'basic'],
    ['blueprint.module_path', 'src/Modules'],
]);

it('refuses old published stub', function () {
    File::ensureDirectoryExists(base_path('stubs/blueprint'));
    file_put_contents(base_path('stubs/blueprint/dto.stub'), <<<'STUB'
<?php

namespace App\Modules\{{ moduleNamespace }}\Data;

use App\Modules\{{ moduleNamespace }}\Http\Requests\{{ requestClass }};

final readonly class {{ dtoClass }}
{
}
STUB);

    $this->artisan('make:dto', ['module' => 'Orders', 'name' => 'OrderData'])
        ->expectsOutputToContain('did not produce a valid class (unknown placeholder {{ moduleNamespace }})')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Data')))->toBeFalse();
});

it('generated data class works', function () {
    $this->artisan('make:dto', ['module' => 'Orders', 'name' => 'DtoCheckData', '--fields' => DTO_FIELDS])->assertExitCode(0);
    $path = app_path('Modules/Orders/Data/DtoCheckData.php');

    $lint = new Symfony\Component\Process\Process([PHP_BINARY, '-l', $path]);
    $lint->run();
    expect($lint->getExitCode())->toBe(0);

    require_once $path;
    $data = new App\Modules\Orders\Data\DtoCheckData('a', 2, null, Carbon\CarbonImmutable::now());

    expect($data->quantity)->toBe(2);
});

it('refuses reserved class names', function (string $name) {
    $this->artisan('make:dto', ['module' => 'Orders', 'name' => $name])
        ->expectsOutputToContain("Invalid class name \"{$name}\".")
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Data')))->toBeFalse();
})->with(['Class', 'List']);

it('refuses a field type named like the class', function () {
    $this->artisan('make:dto', ['module' => 'Orders', 'name' => 'OrderData', '--fields' => 'x:Billing\OrderData'])
        ->expectsOutputToContain('Field type Billing\OrderData has the same short name as the class OrderData.')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Data')))->toBeFalse();
});

it('refuses dot module names', function (string $module) {
    $this->artisan('make:dto', ['module' => $module, 'name' => 'Weird'])
        ->expectsOutputToContain("Module \"{$module}\" not found")
        ->assertExitCode(1);

    expect(file_exists(app_path('Modules/Data/Weird.php')))->toBeFalse()
        ->and(file_exists(app_path('Data/Weird.php')))->toBeFalse();
})->with(['.', '..']);

it('refuses module folders that are not namespace names', function () {
    File::ensureDirectoryExists(app_path('Modules/shared-utils'));

    $this->artisan('make:dto', ['module' => 'shared-utils', 'name' => 'ThingData'])
        ->expectsOutputToContain('Module folder "shared-utils" is not a valid PHP namespace name. Rename it (e.g. SharedUtils) and try again.')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/shared-utils/Data')))->toBeFalse();
});

it('suggests a clean data name for Dto suffixes', function (string $name) {
    $this->artisan('make:dto', ['module' => 'Orders', 'name' => $name])
        ->expectsOutputToContain('Tip: data classes are usually named like OrderData.')
        ->assertExitCode(0);
})->with(['OrderDto', 'OrderDTO']);
