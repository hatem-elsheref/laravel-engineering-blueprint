<?php

it('documents production defaults in the new-app script', function (): void {
    $script = file_get_contents(__DIR__.'/../../bin/new-app');

    expect($script)
        ->toContain('--demo')
        ->toContain('route_auth')
        ->toContain('route_auth_web')
        ->toContain('--permissions')
        ->toContain('spatie/laravel-permission')
        ->toContain('laravel/sanctum');
});
