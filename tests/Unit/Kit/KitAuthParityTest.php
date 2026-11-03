<?php

it('documents ActsAsApiUser instead of calling Sanctum directly in kit skills', function (): void {
    $root = dirname(__DIR__, 3);
    $skills = [
        $root.'/kit/skills/laravel-engineering/SKILL.md',
        $root.'/kit/skills/leb-generate-crud/SKILL.md',
    ];

    foreach ($skills as $path) {
        $contents = file_get_contents($path);

        expect($contents)->toContain('ActsAsApiUser')
            ->and($contents)->not->toContain('Sanctum::actingAs')
            ->and($contents)->not->toContain('manual sanctum');
    }
});

it('does not claim package route_auth defaults to sanctum in kit markdown', function (): void {
    $root = dirname(__DIR__, 3);
    $forbidden = [
        'route_auth defaults to sanctum',
        'default route_auth is sanctum',
        '`route_auth` => `sanctum` globally',
    ];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/kit', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! str_ends_with($file->getPathname(), '.md')) {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        $relative = substr($file->getPathname(), strlen($root) + 1);

        foreach ($forbidden as $phrase) {
            expect($contents)->not->toContain($phrase, $relative);
        }
    }
});
