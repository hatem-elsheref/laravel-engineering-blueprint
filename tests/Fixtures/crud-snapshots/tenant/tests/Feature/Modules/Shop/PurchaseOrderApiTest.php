<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Shop\Models\PurchaseOrder;
use Best\Blueprint\Testing\InteractsWithTenancy;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(InteractsWithTenancy::class, DatabaseMigrations::class, Best\Blueprint\Testing\ActsAsApiUser::class);

beforeEach(function (): void {
    $this->tenant = $this->createTestTenant();
    $this->user = User::factory()->create();
});

it('denies guests', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->getJson($this->tenantUrl('/api/purchase-orders'))->assertForbidden();
    $this->postJson($this->tenantUrl('/api/purchase-orders'), PurchaseOrder::factory()->raw())->assertForbidden();
    $this->getJson($this->tenantUrl("/api/purchase-orders/{$purchaseOrder->id}"))->assertForbidden();
    $this->putJson($this->tenantUrl("/api/purchase-orders/{$purchaseOrder->id}"), PurchaseOrder::factory()->raw())->assertForbidden();
    $this->deleteJson($this->tenantUrl("/api/purchase-orders/{$purchaseOrder->id}"))->assertForbidden();
});

it('denies authenticated users until PurchaseOrderPolicy allows access', function (): void {
    PurchaseOrder::factory()->create();

    $this->actAsApiUser($this->user)->getJson($this->tenantUrl('/api/purchase-orders'))->assertForbidden();
});

it('hides which purchase-orders exist from guests', function (): void {
    $this->getJson($this->tenantUrl('/api/purchase-orders/999999'))->assertForbidden();
    $this->actAsApiUser($this->user)->getJson($this->tenantUrl('/api/purchase-orders/999999'))->assertNotFound();
});

it('is not served on the central domain', function (): void {
    $this->actAsApiUser($this->user)->getJson(url('/api/purchase-orders'))->assertNotFound();
});

