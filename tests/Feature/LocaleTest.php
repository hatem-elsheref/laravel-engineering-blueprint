<?php

use Best\Blueprint\Features\FeatureDisabled;
use Best\Blueprint\Settings\SettingAdmin;
use Best\Blueprint\Tests\Fixtures\LocaleSettings;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config()->set('blueprint.locales', ['en', 'ar']);
    Route::middleware('web')->get('/hello', fn () => app()->getLocale());
    Route::middleware('api')->get('/api/hello', fn () => ['locale' => app()->getLocale()]);
    Route::middleware('api')->get('/api/blocked', fn () => throw FeatureDisabled::key('orders'));
});

it('uses the Accept-Language header for api requests', function (string $header, string $locale) {
    $this->getJson('/api/hello', ['Accept-Language' => $header])->assertJsonPath('locale', $locale);
})->with([
    ['ar', 'ar'],
    ['fr-FR,ar;q=0.8,en;q=0.5', 'ar'],
    ['ar-EG', 'ar'],
    ['fr', 'en'],
]);

it('uses the visitor choice on web pages', function () {
    $this->withSession(['locale' => 'ar'])->get('/hello')->assertSee('ar');
    $this->withSession(['locale' => 'fr'])->get('/hello')->assertSee('en');
});

it('switches language with the locale route', function () {
    $this->from('/hello')->get('/locale/ar')->assertRedirect('/hello')->assertSessionHas('locale', 'ar');
    $this->get('/locale/fr')->assertNotFound();
});

it('falls back to the app language setting', function () {
    require_once __DIR__.'/../Fixtures/LocaleSettings.php';
    config()->set('blueprint.profile', 'saas');
    config()->set('blueprint.settings', LocaleSettings::class);
    useTestFlags();
    config()->set('blueprint.settings', LocaleSettings::class);
    createFlagTables();
    app(SettingAdmin::class)->set('app.language', 'ar');
    app()->forgetScopedInstances();

    $this->get('/hello')->assertSee('ar');
    $this->withSession(['locale' => 'en'])->get('/hello')->assertSee('en');
});

it('ignores languages that are not configured', function () {
    config()->set('blueprint.locales', ['en']);

    $this->getJson('/api/hello', ['Accept-Language' => 'ar'])->assertJsonPath('locale', 'en');
});

it('ships arabic for the package strings', function () {
    app()->setLocale('ar');

    expect(__('Edit'))->toBe('تعديل')
        ->and(__(':item created.', ['item' => 'X']))->toBe('تم إنشاء X.');

    $this->getJson('/api/blocked', ['Accept-Language' => 'ar'])->assertForbidden()->assertJsonPath('message', 'هذه الميزة غير مفعّلة.');
});
