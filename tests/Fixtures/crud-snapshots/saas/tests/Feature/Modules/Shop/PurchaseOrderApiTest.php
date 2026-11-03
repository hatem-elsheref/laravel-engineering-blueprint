<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Shop\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class, Best\Blueprint\Testing\ActsAsApiUser::class);

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

it('denies authenticated users until PurchaseOrderPolicy allows access', function (): void {
    PurchaseOrder::factory()->create();

    $this->actAsApiUser($this->user)->getJson('/api/purchase-orders')->assertForbidden();
    $this->actAsApiUser($this->user)->postJson('/api/purchase-orders', PurchaseOrder::factory()->raw())->assertForbidden();
});

it('hides which purchase-orders exist from guests', function (): void {
    $this->getJson('/api/purchase-orders/999999')->assertForbidden();
    $this->actAsApiUser($this->user)->getJson('/api/purchase-orders/999999')->assertNotFound();
});

