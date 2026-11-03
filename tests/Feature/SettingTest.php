<?php

use App\Models\User;
use Best\Blueprint\Facades\Setting;
use Best\Blueprint\Settings\InvalidSettingValue;
use Best\Blueprint\Settings\SettingAdmin;
use Best\Blueprint\Settings\SettingNotEditable;
use Best\Blueprint\Settings\UnknownSetting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    require_once __DIR__.'/../Fixtures/User.php';
    config()->set('blueprint.profile', 'saas');
    useTestFlags();
    createFlagTables();
    Gate::define('manage-settings', fn ($user) => $user->name === 'owner');
});

function owner(): User
{
    $user = new User;
    $user->name = 'owner';

    return $user;
}

it('returns defaults with the right type', function () {
    expect(Setting::get('app.name'))->toBe('My App')
        ->and(Setting::get('orders.limit'))->toBe(10)
        ->and(Setting::get('orders.rate'))->toBe(1.5)
        ->and(Setting::get('orders.auto_confirm'))->toBeFalse()
        ->and(Setting::get('orders.statuses'))->toBe(['new']);
});

it('rejects unknown keys', function () {
    expect(fn () => Setting::get('nope'))->toThrow(UnknownSetting::class, 'Unknown setting "nope".');
});

it('stores and reads typed values', function (string $key, mixed $input, mixed $stored) {
    $this->actingAs(owner());

    Setting::set($key, $input);

    expect(Setting::get($key))->toBe($stored);
})->with([
    ['app.name', 'Acme', 'Acme'],
    ['orders.limit', 5, 5],
    ['orders.limit', '5', 5],
    ['orders.rate', '2.5', 2.5],
    ['orders.rate', 3, 3.0],
    ['orders.statuses', ['new', 'paid'], ['new', 'paid']],
]);

it('rejects values of the wrong type', function (string $key, mixed $input) {
    $this->actingAs(owner());

    expect(fn () => Setting::set($key, $input))->toThrow(InvalidSettingValue::class);
})->with([
    ['orders.limit', '5.5'],
    ['orders.limit', 'x'],
    ['orders.limit', true],
    ['orders.rate', 'x'],
    ['orders.statuses', 'new'],
    ['app.name', ['x']],
]);

it('never lets tenant code change platform settings', function () {
    $this->actingAs(owner());

    expect(fn () => Setting::set('orders.auto_confirm', true))->toThrow(SettingNotEditable::class, 'Setting "orders.auto_confirm" can only be changed by the platform.');
});

it('refuses tenant settings without an ability', function () {
    $this->actingAs(owner());

    expect(fn () => Setting::set('app.open', 'y'))->toThrow(SettingNotEditable::class, 'Setting "app.open" has no ability');
});

it('checks the ability', function () {
    $other = new User;
    $other->name = 'staff';
    $this->actingAs($other);

    expect(fn () => Setting::set('app.name', 'Hack'))->toThrow(AuthorizationException::class);
});

it('denies writes without a user', function () {
    expect(fn () => Setting::set('app.name', 'Hack'))->toThrow(AuthorizationException::class);
});

it('lets the platform set any setting with type checks', function () {
    app(SettingAdmin::class)->set('orders.auto_confirm', true);

    expect(Setting::get('orders.auto_confirm'))->toBeTrue()
        ->and(fn () => app(SettingAdmin::class)->set('orders.limit', 'x'))->toThrow(InvalidSettingValue::class);
});

it('accepts boolean spellings', function (mixed $input, bool $expected) {
    app(SettingAdmin::class)->set('orders.auto_confirm', $input);

    expect(Setting::get('orders.auto_confirm'))->toBe($expected);
})->with([[true, true], [false, false], [1, true], [0, false], ['1', true], ['0', false], ['true', true], ['false', false]]);

it('lists every setting', function () {
    expect(array_keys(Setting::all()))->toBe(['app.name', 'app.open', 'orders.limit', 'orders.rate', 'orders.auto_confirm', 'orders.statuses']);
});

it('stores json', function () {
    app(SettingAdmin::class)->set('orders.statuses', ['a']);

    expect(DB::table('settings')->where('key', 'orders.statuses')->value('value'))->toBe('["a"]');
});
