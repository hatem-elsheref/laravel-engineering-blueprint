<?php

use Best\Blueprint\Schema\EntitySchemaLoader;
use Best\Blueprint\Schema\SchemaException;
use Best\Blueprint\Support\Modules;

it('loads the package example schema', function (): void {
    config()->set('blueprint.profile', 'modular');
    config()->set('blueprint.module_path', 'app/Modules');

    $path = dirname(__DIR__, 3).'/schemas/examples/orders/order.yaml';

    [$fields, $meta] = (new EntitySchemaLoader)->load($path, 'Orders', 'Order', new Modules);

    $quantity = collect($fields)->first(fn ($f) => $f->column === 'quantity');
    $tags = collect($fields)->first(fn ($f) => $f->column === 'tags');

    expect($fields)->toHaveCount(6)
        ->and($meta->softDeletes)->toBeTrue()
        ->and($meta->indexes)->toBe([['status']])
        ->and($meta->unique)->toBe([])
        ->and($fields[0]->columnUnique)->toBeTrue()
        ->and($fields[0]->validationMin)->toBe(2)
        ->and($fields[0]->validationMax)->toBe(120)
        ->and($quantity->hasSchemaDefault)->toBeTrue()
        ->and($quantity->schemaDefault)->toBe(1)
        ->and($tags->castOverride)->toBe('collection')
        ->and($tags->schemaDefault)->toBe([])
        ->and($meta->usesHasMany)->toBeTrue()
        ->and($meta->modelRelations)->toContain('function items()')
        ->and($meta->modelRelations)->toContain('hasMany(OrderItem::class')
        ->and($meta->enumFiles)->toHaveCount(1)
        ->and($meta->enumFiles[0]['path'])->toBe('app/Modules/Orders/Enums/OrderStatus.php')
        ->and($meta->modelScopes)->toContain('scopePaid')
        ->and($meta->modelScopes)->toContain("->where('status', 'paid')")
        ->and($meta->hasQueryFilters)->toBeTrue()
        ->and(collect($fields)->first(fn ($f) => $f->column === 'note')->resourceHidden)->toBeTrue();
});

it('merges a schema profile before entity fields', function (): void {
    config()->set('blueprint.profile', 'modular');

    $schemaRoot = sys_get_temp_dir().'/leb-profiles-'.uniqid('', true);
    mkdir($schemaRoot.'/profiles', 0777, true);
    file_put_contents($schemaRoot.'/profiles/catalog.yaml', <<<'YAML'
fields:
  - name: name
    type: string
  - name: sku
    type: string
YAML);
    config()->set('blueprint.schema_paths', [$schemaRoot]);

    $path = sys_get_temp_dir().'/leb-schema-'.uniqid('', true).'.yaml';
    file_put_contents($path, "profile: catalog\nfields:\n  - name: price\n    type: decimal\n");

    try {
        [$fields] = (new EntitySchemaLoader)->load($path, 'Catalog', 'Product', new Modules);
    } finally {
        @unlink($path);
        @unlink($schemaRoot.'/profiles/catalog.yaml');
        @rmdir($schemaRoot.'/profiles');
        @rmdir($schemaRoot);
    }

    expect(collect($fields)->pluck('column')->all())->toBe(['name', 'sku', 'price']);
});

it('rejects scopes on unknown columns', function (): void {
    config()->set('blueprint.profile', 'modular');

    $path = sys_get_temp_dir().'/leb-schema-'.uniqid('', true).'.yaml';
    file_put_contents($path, "fields:\n  - name: name\n    type: string\nscopes:\n  - name: x\n    column: missing\n    equals: y\n");

    try {
        (new EntitySchemaLoader)->load($path, 'Orders', 'Order', new Modules);
    } finally {
        @unlink($path);
    }
})->throws(SchemaException::class);

it('rejects a missing schema file', function (): void {
    config()->set('blueprint.profile', 'modular');

    (new EntitySchemaLoader)->load('missing.yaml', 'Orders', 'Order', new Modules);
})->throws(SchemaException::class);

it('rejects foreignId without references', function (): void {
    config()->set('blueprint.profile', 'modular');

    $path = sys_get_temp_dir().'/leb-schema-'.uniqid('', true).'.yaml';
    file_put_contents($path, "fields:\n  - name: user_id\n    type: foreignId\n");

    try {
        (new EntitySchemaLoader)->load($path, 'Orders', 'Order', new Modules);
    } finally {
        @unlink($path);
    }
})->throws(SchemaException::class);

it('rejects min on bool fields', function (): void {
    config()->set('blueprint.profile', 'modular');

    $path = sys_get_temp_dir().'/leb-schema-'.uniqid('', true).'.yaml';
    file_put_contents($path, "fields:\n  - name: active\n    type: bool\n    min: 1\n");

    try {
        (new EntitySchemaLoader)->load($path, 'Orders', 'Order', new Modules);
    } finally {
        @unlink($path);
    }
})->throws(SchemaException::class);

it('rejects cast on non-array fields', function (): void {
    config()->set('blueprint.profile', 'modular');

    $path = sys_get_temp_dir().'/leb-schema-'.uniqid('', true).'.yaml';
    file_put_contents($path, "fields:\n  - name: title\n    type: string\n    cast: collection\n");

    try {
        (new EntitySchemaLoader)->load($path, 'Orders', 'Order', new Modules);
    } finally {
        @unlink($path);
    }
})->throws(SchemaException::class);
