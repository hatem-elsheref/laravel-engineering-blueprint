<?php

use Best\Blueprint\Support\RouteFile;

const MODULE_API_ROUTES = <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Loaded with the "api" middleware group and the /api prefix.
// Route::get('/orders', ListController::class);

PHP;

it('adds use in sorted position', function () {
    $contents = str_replace("use Illuminate\\Support\\Facades\\Route;\n", "use App\\Other;\nuse Illuminate\\Support\\Facades\\Route;\n", MODULE_API_ROUTES);

    $result = RouteFile::addResource($contents, 'App\Modules\Orders\Controllers\OrderController', "Route::apiResource('orders', OrderController::class);");

    expect($result)->toContain("use App\\Modules\\Orders\\Controllers\\OrderController;\nuse App\\Other;\nuse Illuminate\\Support\\Facades\\Route;\n")
        ->and($result)->toEndWith("// Route::get('/orders', ListController::class);\n\nRoute::apiResource('orders', OrderController::class);\n");
});

it('adds use after declare when none', function () {
    $result = RouteFile::addResource("<?php\n\ndeclare(strict_types=1);\n\n// routes\n", 'App\X\FooController', "Route::apiResource('foos', FooController::class);");

    expect($result)->toBe("<?php\n\ndeclare(strict_types=1);\n\nuse App\\X\\FooController;\n\n// routes\n\nRoute::apiResource('foos', FooController::class);\n");
});

it('adds a second entity', function () {
    $once = RouteFile::addResource(MODULE_API_ROUTES, 'App\M\OrderController', "Route::apiResource('orders', OrderController::class);");
    $twice = RouteFile::addResource($once, 'App\M\InvoiceController', "Route::apiResource('invoices', InvoiceController::class);");

    expect($twice)->toContain("use App\\M\\InvoiceController;\nuse App\\M\\OrderController;\nuse Illuminate\\Support\\Facades\\Route;\n")
        ->and($twice)->toEndWith("Route::apiResource('orders', OrderController::class);\n\nRoute::apiResource('invoices', InvoiceController::class);\n");
});

it('handles windows line endings', function () {
    $crlf = str_replace("\n", "\r\n", MODULE_API_ROUTES);

    $result = RouteFile::addResource($crlf, 'App\M\FooController', "Route::apiResource('foos', FooController::class);");

    expect($result)->toContain("use App\\M\\FooController;\r\nuse Illuminate\\Support\\Facades\\Route;\r\n")
        ->and($result)->toEndWith("\r\n\r\nRoute::apiResource('foos', FooController::class);\r\n")
        ->and(str_replace("\r\n", '', $result))->not->toContain("\n");
});

it('handles declare on the php tag line', function () {
    $result = RouteFile::addResource("<?php declare(strict_types=1);\n\n// routes\n", 'App\M\FooController', "Route::apiResource('foos', FooController::class);");

    expect($result)->toBe("<?php declare(strict_types=1);\n\nuse App\\M\\FooController;\n\n// routes\n\nRoute::apiResource('foos', FooController::class);\n");
});

it('refuses files it cannot place an import in', function () {
    expect(fn () => RouteFile::addResource("Route::get('/x', fn () => 1);\n", 'App\M\FooController', "Route::apiResource('foos', FooController::class);"))
        ->toThrow(RuntimeException::class);
});
