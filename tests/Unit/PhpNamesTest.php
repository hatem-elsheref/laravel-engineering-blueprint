<?php

use Best\Blueprint\Support\PhpNames;

it('flags reserved names case-insensitively', function (string $name) {
    expect(PhpNames::isReserved($name))->toBeTrue();
})->with(['Void', 'class', 'List', 'Print', 'Clone', 'Match', 'Int', 'String', 'Mixed', 'Never', 'Self', 'Static', 'Null', 'Iterable']);

it('allows normal names', function (string $name) {
    expect(PhpNames::isReserved($name))->toBeFalse();
})->with(['CreateOrder', 'OrderData', 'Listing', 'Classic']);
