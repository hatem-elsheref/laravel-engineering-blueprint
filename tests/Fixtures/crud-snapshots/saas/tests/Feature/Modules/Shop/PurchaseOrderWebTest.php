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

it('denies authenticated users until PurchaseOrderPolicy allows access', function (): void {
    $this->actingAs($this->user)->get(url('/purchase-orders'))->assertForbidden();
    $this->actingAs($this->user)->get(url('/purchase-orders/create'))->assertForbidden();
});

it('hides which purchase-orders exist from guests', function (): void {
    $this->get(url('/purchase-orders/999999'))->assertForbidden();
    $this->actingAs($this->user)->get(url('/purchase-orders/999999'))->assertNotFound();
});

