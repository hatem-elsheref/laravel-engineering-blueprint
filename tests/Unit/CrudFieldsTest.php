<?php

use Best\Blueprint\Support\CrudFields;
use Best\Blueprint\Support\FieldException;

it('maps each type', function (string $spec, array $expected) {
    $field = CrudFields::parse($spec)[0];

    expect([
        'migration' => $field->migration(),
        'cast' => $field->cast(),
        'rules' => $field->rules(),
        'factory' => $field->factory(),
        'fromRequest' => $field->fromRequest(),
    ])->toBe($expected);
})->with([
    'string' => ['name:string', [
        'migration' => "\$table->string('name')",
        'cast' => null,
        'rules' => ['required', 'string', 'max:255'],
        'factory' => 'fake()->words(3, true)',
        'fromRequest' => "\$request->string('name')->toString()",
    ]],
    'int' => ['quantity:int', [
        'migration' => "\$table->integer('quantity')",
        'cast' => 'integer',
        'rules' => ['required', 'integer', 'between:-2147483648,2147483647'],
        'factory' => 'fake()->numberBetween(1, 100)',
        'fromRequest' => "\$request->integer('quantity')",
    ]],
    'float' => ['price:float', [
        'migration' => "\$table->decimal('price', 12, 2)",
        'cast' => 'float',
        'rules' => ['required', 'numeric', 'between:-9999999999.99,9999999999.99'],
        'factory' => 'fake()->randomFloat(2, 1, 1000)',
        'fromRequest' => "\$request->float('price')",
    ]],
    'bool' => ['active:bool', [
        'migration' => "\$table->boolean('active')",
        'cast' => 'boolean',
        'rules' => ['required', 'boolean'],
        'factory' => 'fake()->boolean()',
        'fromRequest' => "\$request->boolean('active')",
    ]],
    'array' => ['tags:array', [
        'migration' => "\$table->json('tags')",
        'cast' => 'array',
        'rules' => ['present', 'array'],
        'factory' => '[]',
        'fromRequest' => "(array) \$request->validated('tags')",
    ]],
    'date' => ['shippedAt:Carbon\CarbonImmutable', [
        'migration' => "\$table->timestamp('shipped_at')",
        'cast' => 'immutable_datetime',
        'rules' => ['required', 'date'],
        'factory' => 'CarbonImmutable::now()',
        'fromRequest' => "CarbonImmutable::parse(\$request->validated('shipped_at'))->setTimezone(config('app.timezone'))",
    ]],
    'nullable int' => ['quantity:?int', [
        'migration' => "\$table->integer('quantity')->nullable()",
        'cast' => 'integer',
        'rules' => ['nullable', 'integer', 'between:-2147483648,2147483647'],
        'factory' => 'fake()->numberBetween(1, 100)',
        'fromRequest' => "\$request->validated('quantity') === null ? null : \$request->integer('quantity')",
    ]],
    'nullable array' => ['tags:?array', [
        'migration' => "\$table->json('tags')->nullable()",
        'cast' => 'array',
        'rules' => ['nullable', 'array'],
        'factory' => '[]',
        'fromRequest' => "\$request->validated('tags') === null ? null : (array) \$request->validated('tags')",
    ]],
]);

it('exposes property, column and type', function () {
    $field = CrudFields::parse('shippedAt:?Carbon\CarbonImmutable')[0];

    expect($field->property)->toBe('shippedAt')
        ->and($field->column)->toBe('shipped_at')
        ->and($field->type)->toBe('?CarbonImmutable')
        ->and($field->nullable)->toBeTrue()
        ->and($field->import)->toBe('Carbon\CarbonImmutable');
});

it('refuses fields make:crud cannot handle', function (string $spec, string $reason) {
    expect(fn () => CrudFields::parse($spec))->toThrow(FieldException::class, $reason);
})->with([
    ['x:App\Status', 'make:crud supports string, int, float, bool, array and Carbon\CarbonImmutable'],
    ['id:int', 'id is created automatically'],
    ['createdAt:Carbon\CarbonImmutable', 'created_at is created automatically'],
    ['updated_at:Carbon\CarbonImmutable', 'updated_at is created automatically'],
    ['name:text', 'unknown type text'],
]);
