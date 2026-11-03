<?php

use Best\Blueprint\Support\CrudField;

it('applies string min max and unique rules', function (): void {
    $field = new CrudField(
        property: 'name',
        column: 'name',
        type: 'string',
        nullable: false,
        import: null,
        validationMin: 2,
        validationMax: 120,
        columnUnique: true,
    );

    expect($field->migration())->toBe("\$table->string('name')->unique()")
        ->and($field->rules(false, 'orders', 'order'))->toBe([
            'required', 'string', 'max:120', 'min:2', 'unique:orders,name',
        ])
        ->and($field->rules(true, 'orders', 'order'))->toContain(
            "Rule::unique('orders', 'name')->ignore(\$this->route('order'))",
        );
});

it('applies regex on string fields', function (): void {
    $field = new CrudField(
        property: 'code',
        column: 'code',
        type: 'string',
        nullable: false,
        import: null,
        validationRegex: '^[A-Z0-9]+$',
    );

    expect($field->rules())->toContain('regex:/^[A-Z0-9]+$/');
});

it('applies schema default to migration and factory', function (): void {
    $field = new CrudField(
        property: 'quantity',
        column: 'quantity',
        type: 'int',
        nullable: false,
        import: null,
        schemaDefault: 1,
        hasSchemaDefault: true,
    );

    expect($field->migration())->toBe("\$table->integer('quantity')->default(1)")
        ->and($field->factory())->toBe('1');
});

it('applies collection cast override', function (): void {
    $field = new CrudField(
        property: 'tags',
        column: 'tags',
        type: 'array',
        nullable: false,
        import: null,
        castOverride: 'collection',
    );

    expect($field->castLine())->toBe("'tags' => 'collection'");
});
