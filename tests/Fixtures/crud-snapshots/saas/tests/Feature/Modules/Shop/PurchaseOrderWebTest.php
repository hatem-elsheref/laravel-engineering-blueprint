<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Shop\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

function shopPurchaseOrderFormInput(array $overrides = []): array
{
    // Factory values as a browser form would send them.
    return array_map(
        fn ($value) => $value instanceof DateTimeInterface ? $value->format('Y-m-d\TH:i') : (is_bool($value) ? (int) $value : $value),
        array_merge(PurchaseOrder::factory()->raw(), $overrides),
    );
}

it('denies guests', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->get(url('/purchase-orders'))->assertForbidden();
    $this->get(url('/purchase-orders/create'))->assertForbidden();
    $this->post(url('/purchase-orders'), shopPurchaseOrderFormInput())->assertForbidden();
    $this->get(url("/purchase-orders/{$purchaseOrder->id}"))->assertForbidden();
    $this->get(url("/purchase-orders/{$purchaseOrder->id}/edit"))->assertForbidden();
    $this->put(url("/purchase-orders/{$purchaseOrder->id}"), shopPurchaseOrderFormInput())->assertForbidden();
    $this->delete(url("/purchase-orders/{$purchaseOrder->id}"))->assertForbidden();
});

it('hides which purchase-orders exist from guests', function (): void {
    $this->get(url('/purchase-orders/999999'))->assertForbidden();
    $this->actingAs($this->user)->get(url('/purchase-orders/999999'))->assertNotFound();
});

it('lists purchase-orders', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->actingAs($this->user)->get(url('/purchase-orders'))->assertOk()->assertSee("/purchase-orders/{$purchaseOrder->id}");
});

it('shows the create form', function (): void {
    $this->actingAs($this->user)->get(url('/purchase-orders/create'))->assertOk()->assertSee('name="_token"', false);
});

it('creates a purchaseOrder', function (): void {
    $this->actingAs($this->user)->post(url('/purchase-orders'), shopPurchaseOrderFormInput())
        ->assertSessionHasNoErrors()
        ->assertRedirectContains('/purchase-orders/');

    expect(PurchaseOrder::count())->toBe(1);
});

it('validates a purchaseOrder', function (): void {
    $this->actingAs($this->user)->post(url('/purchase-orders'), [])
        ->assertSessionHasErrors(['name', 'quantity', 'active', 'tags']);
});

it('shows and edits a purchaseOrder', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->actingAs($this->user)->get(url("/purchase-orders/{$purchaseOrder->id}"))->assertOk();
    $this->actingAs($this->user)->get(url("/purchase-orders/{$purchaseOrder->id}/edit"))->assertOk();
});

it('updates a purchaseOrder', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->travel(1)->minutes();

    $this->actingAs($this->user)->put(url("/purchase-orders/{$purchaseOrder->id}"), shopPurchaseOrderFormInput())
        ->assertSessionHasNoErrors()
        ->assertRedirectContains("/purchase-orders/{$purchaseOrder->id}");

    expect($purchaseOrder->fresh()->updated_at->gt($purchaseOrder->updated_at))->toBeTrue();
});

it('deletes a purchaseOrder', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->actingAs($this->user)->delete(url("/purchase-orders/{$purchaseOrder->id}"))->assertRedirectContains('/purchase-orders');

    expect(PurchaseOrder::count())->toBe(0);
});

