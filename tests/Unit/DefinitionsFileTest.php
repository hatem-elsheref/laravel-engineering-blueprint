<?php

use Best\Blueprint\Permissions\DefinitionsFile;
use Best\Blueprint\Permissions\InvalidPermissionDefinition;
use Best\Blueprint\Support\StubRenderer;
use Symfony\Component\Process\Process;

const CRUD_ABILITIES = ['view', 'create', 'update', 'delete'];

function generatedDefinitions(): string
{
    return app(StubRenderer::class)->render('permissions-definitions.stub', [
        'namespace' => 'App\Shared\Permissions', 'class' => 'Permissions', 'side' => 'app',
    ]);
}

/** Loads the source under a unique class name and returns what groups() returns. */
function definedGroups(string $source): array
{
    $class = 'DefinitionsFileTest'.uniqid();
    $file = sys_get_temp_dir()."/{$class}.php";
    file_put_contents($file, str_replace(['namespace App\Shared\Permissions;', 'final class Permissions'], ['', "final class {$class}"], $source));

    $lint = new Process([PHP_BINARY, '-l', $file]);
    $lint->run();
    expect($lint->getExitCode())->toBe(0, $lint->getOutput());

    require $file;
    unlink($file);

    return $class::groups();
}

it('adds a group to the generated, empty groups()', function () {
    $source = DefinitionsFile::addGroup(generatedDefinitions(), 'orders', CRUD_ABILITIES);

    expect($source)->toContain("        return [\n            // 'products' => ['view', 'create', 'update', 'delete'],\n            'orders' => ['view', 'create', 'update', 'delete'],\n        ];")
        ->and(DefinitionsFile::hasGroup($source, 'orders'))->toBeTrue()
        ->and(definedGroups($source))->toBe(['orders' => CRUD_ABILITIES])
        ->and(substr_count($source, "'orders' =>"))->toBe(1);
});

it('adds a group as the last entry of a non-empty groups()', function () {
    $source = DefinitionsFile::addGroup(generatedDefinitions(), 'orders', CRUD_ABILITIES);
    $source = DefinitionsFile::addGroup($source, 'purchase-orders', CRUD_ABILITIES);

    expect(definedGroups($source))->toBe(['orders' => CRUD_ABILITIES, 'purchase-orders' => CRUD_ABILITIES])
        ->and(DefinitionsFile::hasGroup($source, 'purchase-orders'))->toBeTrue();
});

it('adds a comma after a last entry without one', function () {
    $source = str_replace("            // 'products' => ['view', 'create', 'update', 'delete'],\n", "            'products' => ['view']\n", generatedDefinitions());

    expect(definedGroups(DefinitionsFile::addGroup($source, 'orders', CRUD_ABILITIES)))
        ->toBe(['products' => ['view'], 'orders' => CRUD_ABILITIES]);
});

it('handles a groups() written on one line', function (string $array, array $expected) {
    $source = preg_replace('/return \[\n.*?\n        \];/s', "return {$array};", generatedDefinitions(), 1);

    expect(definedGroups(DefinitionsFile::addGroup($source, 'orders', CRUD_ABILITIES)))->toBe($expected);
})->with([
    'empty' => ['[]', ['orders' => CRUD_ABILITIES]],
    'one entry' => ["['products' => ['view', 'update']]", ['products' => ['view', 'update'], 'orders' => CRUD_ABILITIES]],
]);

it('leaves roles() alone', function () {
    $source = DefinitionsFile::addGroup(generatedDefinitions(), 'orders', CRUD_ABILITIES);

    expect(substr($source, strpos($source, 'function roles')))->toBe(substr(generatedDefinitions(), strpos(generatedDefinitions(), 'function roles')));
});

it('only counts real groups() keys', function () {
    $source = generatedDefinitions();

    expect(DefinitionsFile::hasGroup($source, 'products'))->toBeFalse() // commented out
        ->and(DefinitionsFile::hasGroup(DefinitionsFile::addGroup($source, 'orders', CRUD_ABILITIES), 'order'))->toBeFalse()
        ->and(DefinitionsFile::hasGroup(str_replace("'admin' => ['*']", "'orders' => ['*']", str_replace('// ', '', $source)), 'orders'))->toBeFalse(); // a role, not a group
});

it('throws when groups() has no array to add to', function (string $source) {
    DefinitionsFile::addGroup($source, 'orders', CRUD_ABILITIES);
})->with([
    'no groups()' => ["<?php\nfinal class Permissions\n{\n}\n"],
    'not an array literal' => [fn () => str_replace("return [\n            // 'products' => ['view', 'create', 'update', 'delete'],\n        ];", 'return self::GROUPS;', generatedDefinitions())],
    'array() syntax' => [fn () => str_replace("return [\n            // 'products' => ['view', 'create', 'update', 'delete'],\n        ];", 'return array();', generatedDefinitions())],
])->throws(InvalidPermissionDefinition::class);

it('reads the abilities of a group', function () {
    $source = DefinitionsFile::addGroup(DefinitionsFile::addGroup(generatedDefinitions(), 'orders', ['view', 'any']), 'shared', ['view']);
    $computed = str_replace("'shared' => ['view'],", "'shared' => self::ABILITIES,", $source);

    expect(DefinitionsFile::groupAbilities($source, 'orders'))->toBe(['view', 'any'])
        ->and(DefinitionsFile::groupAbilities($source, 'missing'))->toBeNull()
        ->and(DefinitionsFile::groupAbilities($computed, 'shared'))->toBeNull()
        ->and(DefinitionsFile::groupAbilities(str_replace("['view', 'any']", '[]', $source), 'orders'))->toBe([]);
});
