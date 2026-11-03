<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Shop\Models\PurchaseOrder;
use Best\Blueprint\Testing\InteractsWithTenancy;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(InteractsWithTenancy::class, DatabaseMigrations::class);

beforeEach(function (): void {
    // A real tenant (database + tenant migrations); users live in the tenant database.
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

it('hides which purchase-orders exist from guests', function (): void {
    $this->getJson($this->tenantUrl('/api/purchase-orders/999999'))->assertForbidden();
    $this->actingAs($this->user)->getJson($this->tenantUrl('/api/purchase-orders/999999'))->assertNotFound();
});

it('is not served on the central domain', function (): void {
    $this->actingAs($this->user)->getJson(url('/api/purchase-orders'))->assertNotFound();
});

it('lists purchase-orders', function (): void {
    PurchaseOrder::factory()->create();

    $this->actingAs($this->user)->getJson($this->tenantUrl('/api/purchase-orders'))->assertOk()->assertJsonCount(1, 'data');
});

it('creates a purchaseOrder', function (): void {
    $this->actingAs($this->user)->postJson($this->tenantUrl('/api/purchase-orders'), PurchaseOrder::factory()->raw())->assertCreated();

    expect(PurchaseOrder::count())->toBe(1);
});

it('validates a purchaseOrder', function (): void {
    $this->actingAs($this->user)->postJson($this->tenantUrl('/api/purchase-orders'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'quantity', 'active', 'tags']);
});

it('shows a purchaseOrder', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->actingAs($this->user)->getJson($this->tenantUrl("/api/purchase-orders/{$purchaseOrder->id}"))
        ->assertOk()
        ->assertJsonPath('data.id', $purchaseOrder->id);
});

it('updates a purchaseOrder', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->travel(1)->minutes();

    $this->actingAs($this->user)->putJson($this->tenantUrl("/api/purchase-orders/{$purchaseOrder->id}"), PurchaseOrder::factory()->raw())->assertOk();

    expect($purchaseOrder->fresh()->updated_at->gt($purchaseOrder->updated_at))->toBeTrue();
});

it('deletes a purchaseOrder', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->actingAs($this->user)->deleteJson($this->tenantUrl("/api/purchase-orders/{$purchaseOrder->id}"))->assertNoContent();

    expect(PurchaseOrder::count())->toBe(0);
});
