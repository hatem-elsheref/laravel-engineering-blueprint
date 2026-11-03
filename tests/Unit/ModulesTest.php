<?php

use Best\Blueprint\Support\Modules;
use Illuminate\Filesystem\Filesystem;

it('reports profile problems', function (?string $profile, ?string $error) {
    config()->set('blueprint.profile', $profile);

    expect((new Modules)->profileError())->toBe($error);
})->with([
    'none' => [null, 'No project profile installed. Run php artisan engineering:install first.'],
    'basic' => ['basic', 'The "basic" profile has no modules. Switch profile with php artisan engineering:install --profile=modular.'],
    'modular' => ['modular', null],
]);

it('validates module_path', function (string $path, bool $valid) {
    config()->set('blueprint.module_path', $path);

    expect((new Modules)->pathError())->toBe($valid ? null : "module_path must be inside app/ (got \"{$path}\").");
})->with([
    ['app/Modules', true],
    ['app/Modules/', true],
    ['app/Domain/Modules', true],
    ['src/Modules', false],
    ['app/My-Modules', false],
    ['app/../x', false],
    ['app', false],
]);

it('derives path and namespace', function (string $config, string $path, string $namespace) {
    config()->set('blueprint.module_path', $config);

    expect((new Modules)->path())->toBe($path)
        ->and((new Modules)->namespace())->toBe($namespace);
})->with([
    ['app/Modules/', 'app/Modules', 'App\Modules'],
    ['app/Domain/Modules', 'app/Domain/Modules', 'App\Domain\Modules'],
]);

it('finds entries case-insensitively', function () {
    $dir = sys_get_temp_dir().'/leb-modules-'.uniqid();
    mkdir($dir.'/Orders', 0777, true);
    touch($dir.'/Notes');

    expect(Modules::entry($dir, 'orders'))->toBe('Orders')
        ->and(Modules::entry($dir, 'NOTES'))->toBe('Notes')
        ->and(Modules::entry($dir, 'Billing'))->toBeNull()
        ->and(Modules::entry($dir.'/missing', 'Orders'))->toBeNull();

    (new Filesystem)->deleteDirectory($dir);
});
