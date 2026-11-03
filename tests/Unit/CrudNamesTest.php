<?php

use Best\Blueprint\Support\CrudNames;

it('derives names', function () {
    $names = CrudNames::for('Order', 'Orders', 'App\Modules\Orders', 'app/Modules/Orders');

    expect($names->table)->toBe('orders')
        ->and($names->uri)->toBe('orders')
        ->and($names->variable)->toBe('order')
        ->and($names->parameter)->toBe('order')
        ->and($names->plural)->toBe('Orders')
        ->and($names->files()['controller'])->toBe('app/Modules/Orders/Controllers/OrderController.php')
        ->and($names->files()['factory'])->toBe('database/factories/Orders/OrderFactory.php')
        ->and($names->files()['test'])->toBe('tests/Feature/Modules/Orders/OrderApiTest.php')
        ->and($names->files()['migration'])->toMatch('/^database\/migrations\/\d{4}_\d{2}_\d{2}_\d{6}_create_orders_table\.php$/');
});

it('derives multi-word names', function () {
    $names = CrudNames::for('PurchaseOrder', 'Shop', 'App\Modules\Shop', 'app/Modules/Shop');

    expect([$names->table, $names->uri, $names->variable, $names->parameter, $names->plural])
        ->toBe(['purchase_orders', 'purchase-orders', 'purchaseOrder', 'purchase_order', 'PurchaseOrders']);
});

it('lists every generated file', function () {
    $files = CrudNames::for('Order', 'Orders', 'App\Modules\Orders', 'app/Modules/Orders')->files();

    expect(array_keys($files))->toBe([
        'model', 'migration', 'factory', 'store', 'update', 'data', 'create', 'updateAction',
        'delete', 'query', 'controller', 'resource', 'policy', 'test',
    ])
        ->and($files['store'])->toBe('app/Modules/Orders/Requests/StoreOrderRequest.php')
        ->and($files['updateAction'])->toBe('app/Modules/Orders/Actions/UpdateOrder.php')
        ->and($files['query'])->toBe('app/Modules/Orders/Queries/ListOrders.php');
});
