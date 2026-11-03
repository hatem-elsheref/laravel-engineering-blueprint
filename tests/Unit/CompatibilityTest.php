<?php

use Best\Blueprint\Support\Compatibility;
use Best\Blueprint\Support\CompatibilityResult;
use Illuminate\Foundation\Application;

it('passes on supported versions', function () {
    $result = (new Compatibility)->check('8.3', '12');

    expect($result)->toBeInstanceOf(CompatibilityResult::class)
        ->and($result->passed)->toBeTrue()
        ->and($result->failed())->toBe([]);
});

it('fails old versions and identifies the failing checks', function () {
    $result = (new Compatibility)->check('8.1', '10');

    expect($result->passed)->toBeFalse()
        ->and(array_column($result->failed(), 'name'))->toBe(['php', 'laravel']);
});

it('reports only the check that fails', function () {
    $result = (new Compatibility)->check('8.3.10', '11.5.0');

    expect(array_column($result->failed(), 'name'))->toBe(['laravel']);
});

it('has the documented result shape', function () {
    $result = (new Compatibility)->check('8.3.1', '12.4.0');

    expect($result->checks)->toBe([
        ['name' => 'php', 'required' => '8.3', 'actual' => '8.3.1', 'passed' => true],
        ['name' => 'laravel', 'required' => '12', 'actual' => '12.4.0', 'passed' => true],
    ]);
});

it('passes newer versions', function () {
    expect((new Compatibility)->check('8.4.0', '13.1.0')->passed)->toBeTrue();
});

it('normalizes pre-release suffixes', function () {
    expect((new Compatibility)->check('8.3.0-dev', '12.0.0-beta1')->passed)->toBeTrue()
        ->and((new Compatibility)->check('8.2.99RC1', '12')->passed)->toBeFalse();
});

it('uses the running environment by default', function () {
    $result = (new Compatibility)->check();

    expect($result->checks[0]['actual'])->toBe(PHP_VERSION)
        ->and($result->checks[1]['actual'])->toBe(Application::VERSION);
});

it('respects custom minimums', function () {
    expect((new Compatibility('99.0', '12'))->check('8.3', '12')->passed)->toBeFalse();
});
