<?php

use Best\Blueprint\Facades\Feature;
use Best\Blueprint\Features\FeatureAdmin;
use Best\Blueprint\Features\FeatureDisabled;
use Best\Blueprint\Features\RequireFeature;
use Best\Blueprint\Features\UnknownFeature;
use Best\Blueprint\Tests\Fixtures\GatedJob;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config()->set('blueprint.profile', 'saas');
    useTestFlags();
    createFlagTables();
    Route::middleware('feature:reports')->get('/reports', fn () => 'reports');
    Route::middleware('feature:nope')->get('/nope', fn () => 'nope');
    Route::middleware('feature:orders,reports')->get('/both', fn () => 'both');
    Route::get('/admin-write', function () {
        app(FeatureAdmin::class)->disable('orders');

        return 'wrote';
    });
});

it('blocks routes of disabled features', function () {
    $this->get('/reports')->assertForbidden();

    app(FeatureAdmin::class)->enable('reports');

    $this->get('/reports')->assertOk()->assertSee('reports');
});

it('treats an unknown feature on a route as a programming error', function () {
    $this->withoutExceptionHandling();

    expect(fn () => $this->get('/nope'))->toThrow(UnknownFeature::class);
});

it('fails jobs of disabled features', function () {
    $job = new class
    {
        public ?Throwable $failedWith = null;

        public bool $ran = false;

        public function fail($e = null): void
        {
            $this->failedWith = $e;
        }
    };

    (new RequireFeature('reports'))->handle($job, function ($job) {
        $job->ran = true;
    });

    expect($job->ran)->toBeFalse()->and($job->failedWith)->toBeInstanceOf(FeatureDisabled::class);

    app(FeatureAdmin::class)->enable('reports');
    (new RequireFeature('reports'))->handle($job, function ($job) {
        $job->ran = true;
    });

    expect($job->ran)->toBeTrue();
});

it('ensures a feature in actions', function () {
    expect(fn () => Feature::ensure('reports'))->toThrow(FeatureDisabled::class);

    app(FeatureAdmin::class)->enable('reports');
    Feature::ensure('reports');

    expect(true)->toBeTrue();
});

it('has a blade directive', function () {
    expect(trim(Blade::render("@feature('reports') yes @endfeature")))->toBe('');

    app(FeatureAdmin::class)->enable('reports');

    expect(trim(Blade::render("@feature('reports') yes @endfeature")))->toBe('yes');
});

it('requires every listed feature', function () {
    app(FeatureAdmin::class)->enable('orders');

    $this->get('/both')->assertForbidden();

    app(FeatureAdmin::class)->enable('reports');

    $this->get('/both')->assertOk();
});

it('refuses admin writes inside http requests in tests too', function () {
    app(FeatureAdmin::class)->enable('orders');

    $this->get('/admin-write')->assertStatus(500);

    expect(Feature::enabled('orders'))->toBeTrue();

    app(FeatureAdmin::class)->disable('orders'); // back in console code after the request

    expect(Feature::enabled('orders'))->toBeFalse();
});

it('fails gated jobs loudly on the sync queue', function () {
    require_once __DIR__.'/../Fixtures/GatedJob.php';
    config()->set('queue.default', 'sync');

    expect(fn () => dispatch(new GatedJob))->toThrow(FeatureDisabled::class);
});
