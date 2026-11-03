<?php

use Best\Blueprint\Tenancy\TenantDomains;

it('builds central domains from APP_URL', function (?string $url, array $expected) {
    expect(TenantDomains::centralFromAppUrl($url))->toBe($expected);
})->with([
    ['https://example.com', ['example.com', 'localhost', '127.0.0.1']],
    ['http://localhost', ['localhost', '127.0.0.1']],
    [null, ['localhost', '127.0.0.1']],
    ['', ['localhost', '127.0.0.1']],
]);

it('ignores port in APP_URL', function () {
    config()->set('app.url', 'http://localhost:8000');
    config()->set('tenancy.central_domains', TenantDomains::centralFromAppUrl('http://localhost:8000'));

    expect(TenantDomains::centralFromAppUrl('http://localhost:8000'))->toBe(['localhost', '127.0.0.1'])
        ->and((new TenantDomains)->url('acme.localhost', '/x'))->toBe('http://acme.localhost:8000/x');
});

it('picks the base domain', function (?string $tenantDomain, array $central, string $base) {
    config()->set('blueprint.tenant_domain', $tenantDomain);
    config()->set('tenancy.central_domains', $central);

    expect((new TenantDomains)->base())->toBe($base)
        ->and((new TenantDomains)->forTenant('acme'))->toBe("acme.{$base}");
})->with([
    ['example.com', ['app.example.com', 'localhost'], 'example.com'],
    [null, ['example.com', 'localhost', '127.0.0.1'], 'example.com'],
    [null, ['127.0.0.1', 'localhost'], 'localhost'],
    [null, ['127.0.0.1'], 'localhost'],
    ['', ['shop.test'], 'shop.test'],
]);

it('validates domains', function (string $domain, ?string $error) {
    config()->set('blueprint.tenant_domain', null);
    config()->set('tenancy.central_domains', ['localhost', '127.0.0.1']);

    expect((new TenantDomains)->domainError($domain))->toBe($error);
})->with([
    ['acme.localhost', null],
    ['acme.other.com', 'Invalid domain "acme.other.com": it must be a subdomain of localhost.'],
    ['localhost', 'Invalid domain "localhost": it must be a subdomain of localhost.'],
    ['ACME.localhost', 'Invalid domain "ACME.localhost": it must be a subdomain of localhost.'],
    ['a..localhost', 'Invalid domain "a..localhost": it must be a subdomain of localhost.'],
]);

it('accepts subdomains of the configured base', function () {
    config()->set('blueprint.tenant_domain', 'example.com');
    config()->set('tenancy.central_domains', ['app.example.com']);

    expect((new TenantDomains)->domainError('acme.example.com'))->toBeNull()
        ->and((new TenantDomains)->domainError('app.example.com'))->not->toBeNull();
});

it('validates names', function (string $name, bool $valid) {
    expect((new TenantDomains)->nameError($name))->toBe($valid ? null : "Invalid tenant name \"{$name}\". Use lowercase letters, numbers and dashes, e.g. acme.");
})->with([
    ['acme', true], ['acme-2', true], ['a', true], ['Acme', false], ['-x', false], ['x-', false], ['a_b', false], [str_repeat('a', 64), false],
]);

it('validates database names', function (string $db, bool $valid) {
    expect((new TenantDomains)->databaseError($db))->toBe($valid ? null : "Invalid database name \"{$db}\". Use letters, numbers and underscores.");
})->with([
    ['tenant_acme', true], ['a-b', false], [str_repeat('a', 65), false], ['', false],
]);
