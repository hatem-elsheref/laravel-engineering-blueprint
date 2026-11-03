<?php

it('links kit crud skill to generator commands and stub tree', function (): void {
    $root = dirname(__DIR__, 3);
    $crud = file_get_contents($root.'/kit/skills/leb-generate-crud/SKILL.md');

    expect($crud)->toContain('make:crud')
        ->and($crud)->toContain('make:module')
        ->and($crud)->toContain('--permissions')
        ->and($crud)->toContain('stubs/')
        ->and($crud)->toContain('docs/AUTHENTICATION.md');
});

it('links laravel-engineering skill to install and architecture commands', function (): void {
    $root = dirname(__DIR__, 3);
    $eng = file_get_contents($root.'/kit/skills/laravel-engineering/SKILL.md');

    expect($eng)->toContain('engineering:install')
        ->and($eng)->toContain('engineering:architecture')
        ->and($eng)->toContain('docs/PHASE-0.md');
});

it('keeps kit cursor rules and agent templates non-empty', function (): void {
    $root = dirname(__DIR__, 3);
    $paths = array_merge(
        glob($root.'/kit/rules/cursor/*') ?: [],
        glob($root.'/kit/rules/templates/*') ?: [],
    );

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path) {
        if (! is_file($path)) {
            continue;
        }

        expect(file_get_contents($path))->not->toBe('');
    }
});
