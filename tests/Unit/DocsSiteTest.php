<?php

use Best\Blueprint\BlueprintServiceProvider;
use Illuminate\Support\Arr;

/*
 * The docs site (site/, GitHub Pages) must keep up with the code: every Artisan command the package
 * registers and every config key needs an entry on both reference pages (English and Arabic).
 */

function docsReferencePages(): array
{
    return [
        'site/reference.html' => __DIR__.'/../../site/reference.html',
        'site/ar/reference.html' => __DIR__.'/../../site/ar/reference.html',
    ];
}

function packageCommandNames(): array
{
    $source = file_get_contents((new ReflectionClass(BlueprintServiceProvider::class))->getFileName());
    preg_match('/->commands\(\[(.*?)\]\)/s', $source, $block);
    preg_match_all('/Console\\\\(\w+)::class/', $block[1], $classes);

    return array_map(function (string $class): string {
        $command = new ReflectionClass("Best\\Blueprint\\Console\\{$class}");
        $signature = $command->getDefaultProperties()['signature'] ?? null;

        return strtok(trim((string) $signature), " \n{");
    }, $classes[1]);
}

it('finds the registered commands', function () {
    expect(packageCommandNames())->toHaveCount(13)->toContain('make:crud', 'engineering:install');
});

it('documents every command on the reference pages', function (string $page, string $path) {
    expect($path)->toBeFile();
    $html = file_get_contents($path);

    foreach (packageCommandNames() as $name) {
        $id = str_replace(':', '-', $name);
        expect(str_contains($html, "id=\"{$id}\""))->toBeTrue("{$page} has no card for {$name}");
    }
})->with(fn () => array_map(null, array_keys(docsReferencePages()), docsReferencePages()));

it('documents every config key on the reference pages', function (string $page, string $path) {
    expect($path)->toBeFile();
    $html = file_get_contents($path);
    $config = require __DIR__.'/../../config/blueprint.php';
    $keys = array_keys(Arr::dot($config));
    $keys = array_map(fn (string $key) => preg_replace('/\.\d+$/', '', $key), $keys); // list values (locales) are one key

    foreach (array_unique($keys) as $key) {
        expect(str_contains($html, "<code>{$key}</code>"))->toBeTrue("{$page} does not document config key {$key}");
    }
})->with(fn () => array_map(null, array_keys(docsReferencePages()), docsReferencePages()));
