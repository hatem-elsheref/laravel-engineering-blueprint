<?php

use Best\Blueprint\Blueprint;
use Best\Blueprint\BlueprintServiceProvider;
use Best\Blueprint\Support\Compatibility;
use Illuminate\Support\ServiceProvider;

it('loads the provider', function () {
    expect(app()->getProvider(BlueprintServiceProvider::class))->not->toBeNull();
});

it('merges package config', function () {
    expect(config('blueprint.module_path'))->toBe('app/Modules')
        ->and(config('blueprint.default_profile'))->toBe('modular')
        ->and(config('blueprint.support'))->toBe(['php' => '8.3', 'laravel' => '12']);
});

it('registers the config publish tag', function () {
    $paths = ServiceProvider::pathsToPublish(BlueprintServiceProvider::class, 'blueprint-config');

    expect(array_values($paths))->toBe([config_path('blueprint.php')])
        ->and(array_key_first($paths))->toEndWith('config/blueprint.php');
});

it('registers the stubs publish tag', function () {
    $paths = ServiceProvider::pathsToPublish(BlueprintServiceProvider::class, 'blueprint-stubs');

    expect(array_values($paths))->toBe([base_path('stubs/blueprint')])
        ->and(is_file(array_key_first($paths).'/model.stub'))->toBeTrue();
});

it('binds compatibility using configured minimums', function () {
    config()->set('blueprint.support', ['php' => '99.0', 'laravel' => '12']);
    app()->forgetInstance(Compatibility::class);

    expect(app(Compatibility::class)->check('8.3', '12')->passed)->toBeFalse();
});

it('falls back to default minimums when support config is missing', function () {
    config()->set('blueprint.support', null);
    app()->forgetInstance(Compatibility::class);

    $checks = app(Compatibility::class)->check('8.3', '12')->checks;

    expect(array_column($checks, 'required'))->toBe(['8.3', '12']);
});

it('reports a version string', function () {
    expect(Blueprint::version())->toBeString()->not->toBeEmpty();
});
