<?php

use Best\Blueprint\Support\ListPerPage;
use Illuminate\Http\Request;

it('uses the configured default page size', function (): void {
    config()->set('blueprint.pagination.per_page', 25);
    config()->set('blueprint.pagination.max_per_page', 100);

    expect(ListPerPage::resolve(null))->toBe(25);
});

it('clamps requested page size to the configured maximum', function (): void {
    config()->set('blueprint.pagination.per_page', 15);
    config()->set('blueprint.pagination.max_per_page', 50);

    expect(ListPerPage::resolve(999))->toBe(50);
});

it('reads per_page from the request query string', function (): void {
    config()->set('blueprint.pagination.per_page', 15);
    config()->set('blueprint.pagination.max_per_page', 100);

    $request = Request::create('/items', 'GET', ['per_page' => '20']);

    expect(ListPerPage::fromRequest($request))->toBe(20);
});
