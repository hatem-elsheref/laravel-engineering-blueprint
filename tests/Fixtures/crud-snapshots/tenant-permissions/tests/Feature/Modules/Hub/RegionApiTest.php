<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Hub\Models\Region;
use Best\Blueprint\Testing\InteractsWithPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class, Best\Blueprint\Testing\ActsAsApiUser::class, InteractsWithPermissions::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->grantPermissions($this->user, 'regions.view', 'regions.create', 'regions.update', 'regions.delete');
});

it('denies guests', function (): void {
    $region = Region::factory()->create();

    $this->getJson('/api/regions')->assertForbidden();
    $this->postJson('/api/regions', Region::factory()->raw())->assertForbidden();
    $this->getJson("/api/regions/{$region->id}")->assertForbidden();
    $this->putJson("/api/regions/{$region->id}", Region::factory()->raw())->assertForbidden();
    $this->deleteJson("/api/regions/{$region->id}")->assertForbidden();
});

it('hides which regions exist from guests', function (): void {
    $this->getJson('/api/regions/999999')->assertForbidden();
    $this->actAsApiUser($this->user)->getJson('/api/regions/999999')->assertNotFound();
});

it('lists regions', function (): void {
    Region::factory()->create();

    $this->actAsApiUser($this->user)->getJson('/api/regions')->assertOk()->assertJsonCount(1, 'data');
});

it('creates a region', function (): void {
    $this->actAsApiUser($this->user)->postJson('/api/regions', Region::factory()->raw())->assertCreated();

    expect(Region::count())->toBe(1);
});

it('validates a region', function (): void {
    $this->actAsApiUser($this->user)->postJson('/api/regions', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('shows a region', function (): void {
    $region = Region::factory()->create();

    $this->actAsApiUser($this->user)->getJson("/api/regions/{$region->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $region->id);
});

it('updates a region', function (): void {
    $region = Region::factory()->create();

    $this->travel(1)->minutes();

    $this->actAsApiUser($this->user)->putJson("/api/regions/{$region->id}", Region::factory()->raw())->assertOk();

    expect($region->fresh()->updated_at->gt($region->updated_at))->toBeTrue();
});

it('deletes a region', function (): void {
    $region = Region::factory()->create();

    $this->actAsApiUser($this->user)->deleteJson("/api/regions/{$region->id}")->assertNoContent();

    expect(Region::count())->toBe(0);
});

it('denies users without the permission', function (): void {
    $user = User::factory()->create();
    $region = Region::factory()->create();

    $this->actAsApiUser($user)->getJson('/api/regions')->assertForbidden();
    $this->actAsApiUser($user)->postJson('/api/regions', Region::factory()->raw())->assertForbidden();
    $this->actAsApiUser($user)->getJson("/api/regions/{$region->id}")->assertForbidden();
    $this->actAsApiUser($user)->putJson("/api/regions/{$region->id}", Region::factory()->raw())->assertForbidden();
    $this->actAsApiUser($user)->deleteJson("/api/regions/{$region->id}")->assertForbidden();
});

it('lets a super-admin in', function (): void {
    $user = User::factory()->create();
    $this->makeSuperAdmin($user);

    $this->actAsApiUser($user)->getJson('/api/regions')->assertOk();
});
