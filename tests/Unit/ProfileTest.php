<?php

use Best\Blueprint\Support\Profile;

it('matches the capability matrix', function (Profile $profile, array $capabilities) {
    expect($profile->capabilities())->toBe($capabilities)
        ->and($profile->usesModules())->toBe(in_array('Modules', $capabilities))
        ->and($profile->usesFeatures())->toBe(in_array('Features', $capabilities))
        ->and($profile->usesSettings())->toBe(in_array('Settings', $capabilities))
        ->and($profile->usesTenancy())->toBe(in_array('Tenancy', $capabilities));
})->with([
    'basic' => [Profile::Basic, []],
    'modular' => [Profile::Modular, ['Modules']],
    'saas' => [Profile::Saas, ['Modules', 'Features', 'Settings']],
    'tenant-saas' => [Profile::TenantSaas, ['Modules', 'Features', 'Settings', 'Tenancy']],
]);

it('lists values in order', function () {
    expect(Profile::values())->toBe(['basic', 'modular', 'saas', 'tenant-saas']);
});

it('rejects unknown values', function () {
    expect(Profile::tryFrom('nope'))->toBeNull();
});

it('has a label per profile', function () {
    expect(array_map(fn (Profile $p) => $p->label(), Profile::cases()))->each->not->toBeEmpty();
});

it('reads the current profile from config', function ($value, $expected) {
    config()->set('blueprint.profile', $value);

    expect(Profile::current())->toBe($expected);
})->with([
    'unset' => [null, null],
    'unknown' => ['enterprise', null],
    'non-string' => [42, null],
    'valid' => ['saas', Profile::Saas],
]);

it('ships config with profile unset and no profiles list', function () {
    expect(config('blueprint.profile'))->toBeNull()
        ->and(config()->has('blueprint.profiles'))->toBeFalse();
});
