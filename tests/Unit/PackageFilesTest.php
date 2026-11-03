<?php

use Best\Blueprint\Blueprint;

it('keeps the manifest version in step with the code', function () {
    $manifest = json_decode(file_get_contents(__DIR__.'/../../blueprint.manifest.json'), true);

    expect($manifest['version'])->toBe(Blueprint::VERSION);
});

it('normalizes installed versions', function (?string $installed, string $expected) {
    expect(Blueprint::normalizeVersion($installed))->toBe($expected);
})->with([
    [null, Blueprint::VERSION],
    ['dev-main', Blueprint::VERSION],
    ['1.0.0+no-version-set', Blueprint::VERSION],
    ['v1.2.0', '1.2.0'],
    ['1.2.0', '1.2.0'],
]);

it('keeps development files out of the Composer package', function () {
    $attributes = file_get_contents(__DIR__.'/../../.gitattributes');

    foreach (['/docs', '/kit', '/tests', '/bin', '/rules.md', '/phpunit.xml', '/.superpowers', '/blueprint.manifest.json'] as $path) {
        expect($attributes)->toMatch('#^'.preg_quote($path, '#').'\s+export-ignore$#m');
    }
});

it('declares the framework it depends on', function () {
    $composer = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);

    expect($composer['require'])->toHaveKey('laravel/framework', '^12.0 || ^13.0')
        ->and($composer['require'])->not->toHaveKey('illuminate/support');
});
