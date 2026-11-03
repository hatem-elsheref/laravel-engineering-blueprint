<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Shop\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('denies guests', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->getJson('/api/purchase-orders')->assertForbidden();
    $this->postJson('/api/purchase-orders', PurchaseOrder::factory()->raw())->assertForbidden();
    $this->getJson("/api/purchase-orders/{$purchaseOrder->id}")->assertForbidden();
    $this->putJson("/api/purchase-orders/{$purchaseOrder->id}", PurchaseOrder::factory()->raw())->assertForbidden();
    $this->deleteJson("/api/purchase-orders/{$purchaseOrder->id}")->assertForbidden();
});

it('hides which purchase-orders exist from guests', function (): void {
    $this->getJson('/api/purchase-orders/999999')->assertForbidden();
    $this->actingAs($this->user)->getJson('/api/purchase-orders/999999')->assertNotFound();
});

it('lists purchase-orders', function (): void {
    PurchaseOrder::factory()->create();

    $this->actingAs($this->user)->getJson('/api/purchase-orders')->assertOk()->assertJsonCount(1, 'data');
});

it('creates a purchaseOrder', function (): void {
    $this->actingAs($this->user)->postJson('/api/purchase-orders', PurchaseOrder::factory()->raw())->assertCreated();

    expect(PurchaseOrder::count())->toBe(1);
});

it('validates a purchaseOrder', function (): void {
    $this->actingAs($this->user)->postJson('/api/purchase-orders', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'quantity', 'active', 'tags']);
});

it('shows a purchaseOrder', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->actingAs($this->user)->getJson("/api/purchase-orders/{$purchaseOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $purchaseOrder->id);
});

it('updates a purchaseOrder', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->travel(1)->minutes();

    $this->actingAs($this->user)->putJson("/api/purchase-orders/{$purchaseOrder->id}", PurchaseOrder::factory()->raw())->assertOk();

    expect($purchaseOrder->fresh()->updated_at->gt($purchaseOrder->updated_at))->toBeTrue();
});

it('deletes a purchaseOrder', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->actingAs($this->user)->deleteJson("/api/purchase-orders/{$purchaseOrder->id}")->assertNoContent();

    expect(PurchaseOrder::count())->toBe(0);
});
