<?php

use Best\Blueprint\Support\ModuleName;

it('normalizes module names', function (string $input, ?string $expected) {
    expect(ModuleName::normalize($input))->toBe($expected);
})->with([
    ['Orders', 'Orders'],
    ['orders', 'Orders'],
    ['purchase-orders', 'PurchaseOrders'],
    ['purchase_orders', 'PurchaseOrders'],
    ['Orders2', 'Orders2'],
    ['123', null],
    ['Order Items!', null],
    ['', null],
    ['   ', null],
    ['Ord/ers', null],
    ['App\\Orders', null],
]);
