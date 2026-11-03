<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Shop\Models\Widget;
use Best\Blueprint\Testing\InteractsWithPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class, Best\Blueprint\Testing\ActsAsApiUser::class, InteractsWithPermissions::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->lebApiSanctum = true;
    $this->grantPermissions($this->user, 'widgets.view', 'widgets.create', 'widgets.update', 'widgets.delete');
});

it('denies guests', function (): void {
    $widget = Widget::factory()->create();

    $this->getJson('/api/widgets')->assertForbidden();
    $this->postJson('/api/widgets', Widget::factory()->raw())->assertForbidden();
    $this->getJson("/api/widgets/{$widget->id}")->assertForbidden();
    $this->putJson("/api/widgets/{$widget->id}", Widget::factory()->raw())->assertForbidden();
    $this->deleteJson("/api/widgets/{$widget->id}")->assertForbidden();
});

it('hides which widgets exist from guests', function (): void {
    $this->getJson('/api/widgets/999999')->assertForbidden();
    $this->actAsApiUser($this->user)->getJson('/api/widgets/999999')->assertNotFound();
});

it('lists widgets', function (): void {
    Widget::factory()->for($this->user)->create();

    $this->actAsApiUser($this->user)->getJson('/api/widgets')->assertOk()->assertJsonCount(1, 'data');
});

it('creates a widget', function (): void {
    $this->actAsApiUser($this->user)->postJson('/api/widgets', Widget::factory()->raw())->assertCreated();

    expect(Widget::count())->toBe(1);
});

it('validates a widget', function (): void {
    $this->actAsApiUser($this->user)->postJson('/api/widgets', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('shows a widget', function (): void {
    $widget = Widget::factory()->for($this->user)->create();

    $this->actAsApiUser($this->user)->getJson("/api/widgets/{$widget->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $widget->id);
});

it('updates a widget', function (): void {
    $widget = Widget::factory()->for($this->user)->create();

    $this->travel(1)->minutes();

    $this->actAsApiUser($this->user)->putJson("/api/widgets/{$widget->id}", Widget::factory()->raw())->assertOk();

    expect($widget->fresh()->updated_at->gt($widget->updated_at))->toBeTrue();
});

it('deletes a widget', function (): void {
    $widget = Widget::factory()->for($this->user)->create();

    $this->actAsApiUser($this->user)->deleteJson("/api/widgets/{$widget->id}")->assertNoContent();

    expect(Widget::count())->toBe(0);
});

it('denies users without the permission', function (): void {
    $user = User::factory()->create();
    $widget = Widget::factory()->create();

    $this->actAsApiUser($user)->getJson('/api/widgets')->assertForbidden();
    $this->actAsApiUser($user)->postJson('/api/widgets', Widget::factory()->raw())->assertForbidden();
    $this->actAsApiUser($user)->getJson("/api/widgets/{$widget->id}")->assertNotFound();
    $this->actAsApiUser($user)->putJson("/api/widgets/{$widget->id}", Widget::factory()->raw())->assertNotFound();
    $this->actAsApiUser($user)->deleteJson("/api/widgets/{$widget->id}")->assertNotFound();
});

it('lets a super-admin in', function (): void {
    $user = User::factory()->create();
    $this->makeSuperAdmin($user);

    $this->actAsApiUser($user)->getJson('/api/widgets')->assertOk();
});

it('denies an owner without the ability', function (): void {
    $this->grantPermissions($this->user, 'widgets.view');
    $widget = Widget::factory()->for($this->user, 'user')->create();

    $this->actAsApiUser($this->user)->getJson("/api/widgets/{$widget->id}")->assertOk();
    $this->actAsApiUser($this->user)->putJson("/api/widgets/{$widget->id}", Widget::factory()->raw())->assertForbidden();
    $this->actAsApiUser($this->user)->deleteJson("/api/widgets/{$widget->id}")->assertForbidden();
});

it("hides other users' widgets", function (): void {
    $widget = Widget::factory()->create();

    $this->actAsApiUser($this->user)->getJson("/api/widgets/{$widget->id}")->assertNotFound();
    $this->actAsApiUser($this->user)->putJson("/api/widgets/{$widget->id}", Widget::factory()->raw())->assertNotFound();
    $this->actAsApiUser($this->user)->deleteJson("/api/widgets/{$widget->id}")->assertNotFound();
    $this->actAsApiUser($this->user)->getJson('/api/widgets')->assertOk()->assertJsonCount(0, 'data');

    expect(Widget::count())->toBe(1);
});

it('lets an any-holder act on every widget', function (): void {
    $this->grantPermissions($this->user, 'widgets.view', 'widgets.create', 'widgets.update', 'widgets.delete', 'widgets.any');
    $widget = Widget::factory()->create();

    $this->actAsApiUser($this->user)->getJson("/api/widgets/{$widget->id}")->assertOk();
    $this->actAsApiUser($this->user)->getJson('/api/widgets')->assertOk()->assertJsonCount(1, 'data');
    $this->actAsApiUser($this->user)->deleteJson("/api/widgets/{$widget->id}")->assertNoContent();
});

it('keeps the owner when an any-holder updates', function (): void {
    $this->grantPermissions($this->user, 'widgets.view', 'widgets.create', 'widgets.update', 'widgets.delete', 'widgets.any');
    $widget = Widget::factory()->create();

    $this->actAsApiUser($this->user)->putJson("/api/widgets/{$widget->id}", Widget::factory()->raw(['user_id' => $this->user->id]))->assertOk();

    expect($widget->fresh()->user_id)->toEqual($widget->user_id);
});

it('lets a super-admin see every widget', function (): void {
    $user = User::factory()->create();
    $this->makeSuperAdmin($user);
    $widget = Widget::factory()->create();
    Widget::factory()->for($user)->create();

    $this->actAsApiUser($user)->getJson('/api/widgets')->assertOk()->assertJsonCount(2, 'data');
});
