<?php

use Best\Blueprint\Blueprint;
use Best\Blueprint\Support\Compatibility;
use Illuminate\Support\Facades\Artisan;

it('is registered', function () {
    expect(Artisan::all())->toHaveKey('engineering:about');
});

it('passes on the current environment', function () {
    $this->artisan('engineering:about')
        ->expectsOutputToContain(Blueprint::NAME)
        ->expectsOutputToContain('✓ PHP >= 8.3')
        ->expectsOutputToContain('✓ Laravel >= 12')
        ->expectsOutputToContain('Environment is compatible.')
        ->assertExitCode(0);
});

it('fails when requirements are not met', function () {
    app()->instance(Compatibility::class, new Compatibility('99.0', '12'));

    $this->artisan('engineering:about')
        ->expectsOutputToContain('✗ PHP >= 99.0')
        ->expectsOutputToContain('✓ Laravel >= 12')
        ->expectsOutputToContain('Environment is not compatible')
        ->assertExitCode(1);
});

it('shows profile not installed', function () {
    $this->artisan('engineering:about')
        ->expectsOutputToContain('Profile: not installed (run php artisan engineering:install)')
        ->assertExitCode(0);
});

it('shows installed profile', function () {
    config()->set('blueprint.profile', 'saas');

    $this->artisan('engineering:about')
        ->expectsOutputToContain('Profile: saas')
        ->assertExitCode(0);
});
