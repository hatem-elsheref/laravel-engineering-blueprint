<?php

use Best\Blueprint\Tenancy\TenantSetup;
use Best\Blueprint\Tests\Fixtures\AppLocaleSettings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    require_once __DIR__.'/../Fixtures/AppLocaleSettings.php';
    useTestFlags();
    config()->set('blueprint.settings', AppLocaleSettings::class);
    File::ensureDirectoryExists(database_path('migrations/tenant'));
    File::copy(__DIR__.'/../../stubs/settings-migration.stub', database_path('migrations/tenant/2026_01_01_000200_create_settings_table.php'));
    app(TenantSetup::class)->run('a', 'a.localhost', 'tenant_a');

    config()->set('blueprint.locales', ['ar', 'en']);
    config()->set('app.locale', 'ar');
    app()->instance('blueprint.default_locale', 'ar');
    Route::middleware(['web', 'tenant'])->get('/which', fn () => app()->getLocale());
    Route::middleware(['web', 'tenant'])->get('/denied', fn () => abort(403));
});

afterEach(function () {
    File::deleteDirectory(database_path('migrations/tenant'));
});

it('uses the app default language on tenant pages', function () {
    $this->get('http://a.localhost/which')->assertSee('ar');
});

it('renders error pages in that language', function () {
    // Laravel's error views translate their text; their <html lang> is fixed (publish them for RTL).
    $this->get('http://a.localhost/denied')->assertForbidden()->assertSee('ممنوع');
});
