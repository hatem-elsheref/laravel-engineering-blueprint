<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $tenant = Tenant::withoutEvents(fn () => Tenant::create(['id' => 'acme']));
    $tenant->domains()->create(['domain' => 'acme.localhost']);
    $tenant->database()->manager()->createDatabase($tenant);

    Route::middleware(['api', 'tenant'])->get('/t', fn () => 'tenant:'.tenant('id'));
    Route::middleware(['api', 'central'])->get('/c', fn () => 'central');
    Route::middleware(['api', 'tenant'])->get('/t/{tenant}', fn (Tenant $tenant) => 'bound');
});

it('serves tenant routes only on tenant domains', function () {
    $this->get('http://localhost/t')->assertNotFound();
    $this->get('http://acme.localhost/t')->assertOk()->assertSee('tenant:acme');
});

it('serves central routes only on central domains', function () {
    $this->get('http://localhost/c')->assertOk()->assertSee('central');
    $this->get('http://acme.localhost/c')->assertNotFound();
});

it('central host gets 404 before binding', function () {
    $this->get('http://localhost/t/acme')->assertNotFound();
});
