<?php

use Best\Blueprint\Schema\EntitySchemaLoader;
use Best\Blueprint\Schema\SchemaException;
use Best\Blueprint\Support\Modules;

it('loads uuid primary keys from schema id', function (): void {
    config()->set('blueprint.profile', 'modular');

    $path = sys_get_temp_dir().'/leb-schema-'.uniqid('', true).'.yaml';
    file_put_contents($path, <<<'YAML'
id: uuid
fields:
  - name: title
    type: string
YAML);

    try {
        [, $meta] = (new EntitySchemaLoader)->load($path, 'Docs', 'Document', new Modules);
    } finally {
        @unlink($path);
    }

    expect($meta->primaryKeyMigrationLine)->toContain("uuid('id')->primary()")
        ->and($meta->modelPrimaryKeyBlock)->toContain('HasUuids')
        ->and($meta->listLatestColumn)->toBe('created_at');
});

it('loads composite primary keys', function (): void {
    config()->set('blueprint.profile', 'modular');

    $path = sys_get_temp_dir().'/leb-schema-'.uniqid('', true).'.yaml';
    file_put_contents($path, <<<'YAML'
primaryKey:
  columns: [warehouse_id, bin_code]
fields:
  - name: warehouse_id
    type: int
  - name: bin_code
    type: string
YAML);

    try {
        [, $meta] = (new EntitySchemaLoader)->load($path, 'Inventory', 'Bin', new Modules);
    } finally {
        @unlink($path);
    }

    expect($meta->primaryKeyMigrationLine)->toBe('')
        ->and($meta->compositePrimaryKeyColumns)->toBe(['warehouse_id', 'bin_code'])
        ->and($meta->modelPrimaryKeyBlock)->toContain('$incrementing = false');
});

it('renders in and between filters', function (): void {
    config()->set('blueprint.profile', 'modular');

    $path = sys_get_temp_dir().'/leb-schema-'.uniqid('', true).'.yaml';
    file_put_contents($path, <<<'YAML'
fields:
  - name: status
    type: string
  - name: price
    type: float
filters:
  - column: status
    type: in
    query: statuses
  - column: price
    type: between
YAML);

    try {
        [, $meta] = (new EntitySchemaLoader)->load($path, 'Shop', 'Product', new Modules);
    } finally {
        @unlink($path);
    }

    expect($meta->queryFilterLines)->toContain('whereIn')
        ->and($meta->queryFilterLines)->toContain('price_min');
});

it('loads custom validation rule classes on fields', function (): void {
    config()->set('blueprint.profile', 'modular');

    $path = sys_get_temp_dir().'/leb-schema-'.uniqid('', true).'.yaml';
    file_put_contents($path, <<<'YAML'
fields:
  - name: code
    type: string
    rules:
      - App\Rules\CorporateCode
YAML);

    try {
        [$fields] = (new EntitySchemaLoader)->load($path, 'Shop', 'Product', new Modules);
    } finally {
        @unlink($path);
    }

    expect($fields[0]->customRuleClasses)->toBe(['App\Rules\CorporateCode'])
        ->and($fields[0]->rules(false, 'products', 'product'))->toContain('new \\App\\Rules\\CorporateCode()');
});

it('rejects id and primaryKey together', function (): void {
    config()->set('blueprint.profile', 'modular');

    $path = sys_get_temp_dir().'/leb-schema-'.uniqid('', true).'.yaml';
    file_put_contents($path, "id: uuid\nprimaryKey:\n  columns: [a, b]\nfields:\n  - name: a\n    type: int\n  - name: b\n    type: int\n");

    try {
        (new EntitySchemaLoader)->load($path, 'X', 'Y', new Modules);
    } finally {
        @unlink($path);
    }
})->throws(SchemaException::class);
