<?php

use Best\Blueprint\Support\RouteFile;

const MODULE_API_ROUTES = <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Loaded with the "api" middleware group and the /api prefix.
// Route::get('/orders', ListController::class);

PHP;

it('adds use after last use', function () {
    $contents = str_replace("use Illuminate\\Support\\Facades\\Route;\n", "use App\\Other;\nuse Illuminate\\Support\\Facades\\Route;\n", MODULE_API_ROUTES);

    $result = RouteFile::addResource($contents, 'App\Modules\Orders\Controllers\OrderController', 'orders', 'OrderController');

    expect($result)->toContain("use Illuminate\\Support\\Facades\\Route;\nuse App\\Modules\\Orders\\Controllers\\OrderController;\n")
        ->and($result)->toEndWith("// Route::get('/orders', ListController::class);\n\nRoute::apiResource('orders', OrderController::class);\n");
});

it('adds use after declare when none', function () {
    $result = RouteFile::addResource("<?php\n\ndeclare(strict_types=1);\n\n// routes\n", 'App\X\FooController', 'foos', 'FooController');

    expect($result)->toBe("<?php\n\ndeclare(strict_types=1);\n\nuse App\\X\\FooController;\n\n// routes\n\nRoute::apiResource('foos', FooController::class);\n");
});

it('adds a second entity', function () {
    $once = RouteFile::addResource(MODULE_API_ROUTES, 'App\M\OrderController', 'orders', 'OrderController');
    $twice = RouteFile::addResource($once, 'App\M\InvoiceController', 'invoices', 'InvoiceController');

    expect($twice)->toContain("use App\\M\\OrderController;\nuse App\\M\\InvoiceController;\n")
        ->and($twice)->toEndWith("Route::apiResource('orders', OrderController::class);\n\nRoute::apiResource('invoices', InvoiceController::class);\n");
});
