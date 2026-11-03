<?php

use Best\Blueprint\Support\FieldException;
use Best\Blueprint\Support\Fields;

it('parses scalars', function () {
    $fields = Fields::parse('name:string, quantity:int,note:?string');

    expect(array_column($fields, 'name'))->toBe(['name', 'quantity', 'note'])
        ->and(array_column($fields, 'type'))->toBe(['string', 'int', '?string'])
        ->and(array_column($fields, 'import'))->toBe([null, null, null]);
});

it('camel-cases names', function () {
    expect(Fields::parse('unit_price:int')[0]['name'])->toBe('unitPrice');
});

it('parses class types', function () {
    $fields = Fields::parse('placedAt:Carbon\CarbonImmutable,at:?\Carbon\CarbonImmutable');

    expect(array_column($fields, 'type'))->toBe(['CarbonImmutable', '?CarbonImmutable'])
        ->and(array_column($fields, 'import'))->toBe(['Carbon\CarbonImmutable', 'Carbon\CarbonImmutable']);
});

it('empty spec means no fields', function (?string $spec) {
    expect(Fields::parse($spec))->toBe([]);
})->with([null, '', '  ']);

it('refuses invalid entries', function (string $spec, string $entry, string $reason) {
    try {
        Fields::parse($spec);
        $this->fail('Expected FieldException');
    } catch (FieldException $e) {
        expect($e->entry)->toBe($entry)
            ->and($e->reason)->toBe($reason)
            ->and($e->getMessage())->toBe("Invalid field \"{$entry}\": {$reason}.");
    }
})->with([
    'empty entry' => ['name:string,,x:int', '', 'empty'],
    'missing type' => ['name', 'name', 'missing type'],
    'invalid name' => ['1x:int', '1x:int', 'invalid name'],
    'unknown type' => ['name:text', 'name:text', 'unknown type text'],
    'duplicate' => ['a:int,a:string', 'a:string', 'duplicate name a'],
    'same short class' => ['a:Foo\Status,b:Bar\Status', 'b:Bar\Status', 'two class types named Status'],
    'rejects reserved names' => ['this:string', 'this:string', 'reserved name this'],
    'rejects invalid class types' => ['a:Carbon\1Bad', 'a:Carbon\1Bad', 'unknown type Carbon\1Bad'],
    'rejects double backslash' => ['a:App\\\\Double', 'a:App\\\\Double', 'unknown type App\\\\Double'],
]);

it('refuses reserved class short names', function (string $spec) {
    expect(fn () => Fields::parse($spec))->toThrow(FieldException::class, 'reserved class name');
})->with(['x:App\Int', 'x:App\String', 'x:?App\Mixed', 'x:App\Self']);
