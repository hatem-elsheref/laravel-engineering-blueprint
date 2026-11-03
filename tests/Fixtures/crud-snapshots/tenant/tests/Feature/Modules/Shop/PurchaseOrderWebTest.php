<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Shop\Models\PurchaseOrder;
use Best\Blueprint\Testing\InteractsWithTenancy;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(InteractsWithTenancy::class, DatabaseMigrations::class);

beforeEach(function (): void {
    $this->tenant = $this->createTestTenant();
    $this->user = User::factory()->create();
});

function shopPurchaseOrderFormInput(array $overrides = []): array
{
    return array_map(
        fn ($value) => $value instanceof DateTimeInterface ? $value->format('Y-m-d\TH:i') : (is_bool($value) ? (int) $value : $value),
        array_merge(PurchaseOrder::factory()->raw(), $overrides),
    );
}

it('denies guests', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->get($this->tenantUrl('/purchase-orders'))->assertForbidden();
    $this->get($this->tenantUrl('/purchase-orders/create'))->assertForbidden();
    $this->post($this->tenantUrl('/purchase-orders'), shopPurchaseOrderFormInput())->assertForbidden();
    $this->get($this->tenantUrl("/purchase-orders/{$purchaseOrder->id}"))->assertForbidden();
    $this->get($this->tenantUrl("/purchase-orders/{$purchaseOrder->id}/edit"))->assertForbidden();
    $this->put($this->tenantUrl("/purchase-orders/{$purchaseOrder->id}"), shopPurchaseOrderFormInput())->assertForbidden();
    $this->delete($this->tenantUrl("/purchase-orders/{$purchaseOrder->id}"))->assertForbidden();
});

it('denies authenticated users until PurchaseOrderPolicy allows access', function (): void {
    $this->actingAs($this->user)->get($this->tenantUrl('/purchase-orders'))->assertForbidden();
});

it('hides which purchase-orders exist from guests', function (): void {
    $this->get($this->tenantUrl('/purchase-orders/999999'))->assertForbidden();
    $this->actingAs($this->user)->get($this->tenantUrl('/purchase-orders/999999'))->assertNotFound();
});

it('is not served on the central domain', function (): void {
    $this->actingAs($this->user)->get(url('/purchase-orders'))->assertNotFound();
});

