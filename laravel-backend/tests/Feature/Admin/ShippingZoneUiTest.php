<?php

declare(strict_types=1);

use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function ordersManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // has orders.manage

    return $user;
}

it('blocks users without orders.manage from creating zones', function () {
    $user = User::factory()->create();
    $user->assignRole('sub-admin'); // orders.view only

    actingAs($user)
        ->post('/admin/shipping/zones', ['name' => 'Sneaky', 'cost' => '10'])
        ->assertForbidden();
});

it('lists shipping zones for staff with orders.view', function () {
    ShippingZone::factory()->create(['name' => 'Inside Dhaka']);
    $user = User::factory()->create();
    $user->assignRole('sub-admin'); // orders.view

    actingAs($user)
        ->get('/admin/shipping/zones')
        ->assertOk();
});

it('creates a shipping zone, storing cost as paisa', function () {
    actingAs(ordersManager())
        ->post('/admin/shipping/zones', [
            'name' => 'Inside Dhaka',
            'cost' => '80.50',
            'status' => '1',
            'position_order' => 1,
        ])
        ->assertRedirect(route('admin.shipping-zones.index'));

    $zone = ShippingZone::query()->where('name', 'Inside Dhaka')->firstOrFail();
    expect($zone->cost->toMinor())->toBe(8050);
    expect($zone->status)->toBeTrue();
});

it('updates a shipping zone', function () {
    $zone = ShippingZone::factory()->create(['name' => 'Old']);

    actingAs(ordersManager())
        ->put("/admin/shipping/zones/{$zone->id}", [
            'name' => 'Outside Dhaka',
            'cost' => '150',
            'status' => '1',
            'position_order' => 2,
        ])
        ->assertRedirect(route('admin.shipping-zones.index'));

    expect($zone->refresh()->name)->toBe('Outside Dhaka');
    expect($zone->cost->toMinor())->toBe(15000);
});

it('deletes a shipping zone', function () {
    $zone = ShippingZone::factory()->create();

    actingAs(ordersManager())
        ->delete("/admin/shipping/zones/{$zone->id}")
        ->assertRedirect(route('admin.shipping-zones.index'));

    expect(ShippingZone::query()->find($zone->id))->toBeNull();
});
