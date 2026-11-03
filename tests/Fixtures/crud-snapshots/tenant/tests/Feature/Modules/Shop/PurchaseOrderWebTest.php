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

    $this->get($this->tenantUrl('/purchase-orders'))->assertForbidden();
    $this->get($this->tenantUrl('/purchase-orders/create'))->assertForbidden();
    $this->post($this->tenantUrl('/purchase-orders'), shopPurchaseOrderFormInput())->assertForbidden();
    $this->get($this->tenantUrl("/purchase-orders/{$purchaseOrder->id}"))->assertForbidden();
    $this->get($this->tenantUrl("/purchase-orders/{$purchaseOrder->id}/edit"))->assertForbidden();
    $this->put($this->tenantUrl("/purchase-orders/{$purchaseOrder->id}"), shopPurchaseOrderFormInput())->assertForbidden();
    $this->delete($this->tenantUrl("/purchase-orders/{$purchaseOrder->id}"))->assertForbidden();
});

it('hides which purchase-orders exist from guests', function (): void {
    $this->get($this->tenantUrl('/purchase-orders/999999'))->assertForbidden();
    $this->actingAs($this->user)->get($this->tenantUrl('/purchase-orders/999999'))->assertNotFound();
});

it('is not served on the central domain', function (): void {
    $this->actingAs($this->user)->get(url('/purchase-orders'))->assertNotFound();
});

it('lists purchase-orders', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->actingAs($this->user)->get($this->tenantUrl('/purchase-orders'))->assertOk()->assertSee("/purchase-orders/{$purchaseOrder->id}");
});

it('shows the create form', function (): void {
    $this->actingAs($this->user)->get($this->tenantUrl('/purchase-orders/create'))->assertOk()->assertSee('name="_token"', false);
});

it('creates a purchaseOrder', function (): void {
    $this->actingAs($this->user)->post($this->tenantUrl('/purchase-orders'), shopPurchaseOrderFormInput())
        ->assertSessionHasNoErrors()
        ->assertRedirectContains('/purchase-orders/');

    expect(PurchaseOrder::count())->toBe(1);
});

it('validates a purchaseOrder', function (): void {
    $this->actingAs($this->user)->post($this->tenantUrl('/purchase-orders'), [])
        ->assertSessionHasErrors(['name', 'quantity', 'active', 'tags']);
});

it('shows and edits a purchaseOrder', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->actingAs($this->user)->get($this->tenantUrl("/purchase-orders/{$purchaseOrder->id}"))->assertOk();
    $this->actingAs($this->user)->get($this->tenantUrl("/purchase-orders/{$purchaseOrder->id}/edit"))->assertOk();
});

it('updates a purchaseOrder', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->travel(1)->minutes();

    $this->actingAs($this->user)->put($this->tenantUrl("/purchase-orders/{$purchaseOrder->id}"), shopPurchaseOrderFormInput())
        ->assertSessionHasNoErrors()
        ->assertRedirectContains("/purchase-orders/{$purchaseOrder->id}");

    expect($purchaseOrder->fresh()->updated_at->gt($purchaseOrder->updated_at))->toBeTrue();
});

it('deletes a purchaseOrder', function (): void {
    $purchaseOrder = PurchaseOrder::factory()->create();

    $this->actingAs($this->user)->delete($this->tenantUrl("/purchase-orders/{$purchaseOrder->id}"))->assertRedirectContains('/purchase-orders');

    expect(PurchaseOrder::count())->toBe(0);
});

