<?php

use Best\Blueprint\Http\Middleware\ScopeSessionToDomain;
use Best\Blueprint\Tenancy\TenantSetup;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    foreach (['a', 'b'] as $id) {
        app(TenantSetup::class)->run($id, "{$id}.localhost", "tenant_{$id}");
    }
});

function requestWithSession(array $data = []): Request
{
    $session = new Store('test', new ArraySessionHandler(10));
    $session->put($data);
    $request = Request::create('/');
    $request->setLaravelSession($session);

    return $request;
}

function runScope(Request $request): string
{
    return (new ScopeSessionToDomain)->handle($request, fn () => response('ok'))->getContent();
}

it('stamps sessions with their tenant and refuses them elsewhere', function () {
    $request = requestWithSession();
    tenancy()->initialize(tenancy()->find('a'));
    expect(runScope($request))->toBe('ok')->and($request->session()->get('_tenant_id'))->toBe('a');

    tenancy()->initialize(tenancy()->find('b'));
    expect(fn () => runScope($request))->toThrow(HttpException::class);
});

it('refuses tenant sessions on central routes', function () {
    tenancy()->end();

    expect(fn () => runScope(requestWithSession(['_tenant_id' => 'a'])))->toThrow(HttpException::class);
});

it('refuses central sessions on tenant routes', function () {
    tenancy()->end();
    $request = requestWithSession();
    expect(runScope($request))->toBe('ok')->and($request->session()->get('_tenant_id'))->toBe('__central__');

    tenancy()->initialize(tenancy()->find('a'));
    expect(fn () => runScope($request))->toThrow(HttpException::class);
});

it('ignores requests without a session', function () {
    expect(runScope(Request::create('/api/x')))->toBe('ok');
});

it('runs on every web route', function () {
    expect(app('router')->getMiddlewareGroups()['web'])->toContain(ScopeSessionToDomain::class);
});
