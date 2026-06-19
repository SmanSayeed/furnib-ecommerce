<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function orderViewer(): User
{
    $user = User::factory()->create();
    $user->assignRole('sub-admin'); // orders.view only

    return $user;
}

function orderManagerUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // orders.manage

    return $user;
}

it('lists orders for staff with orders.view', function () {
    Order::factory()->create();

    actingAs(orderViewer())
        ->get('/admin/orders')
        ->assertOk();
});

it('filters orders by status', function () {
    Order::factory()->create(['status' => 'pending']);
    Order::factory()->status('delivered')->create();

    actingAs(orderViewer())
        ->get('/admin/orders?status=delivered')
        ->assertOk();
});

it('shows an order for staff with orders.view', function () {
    $order = Order::factory()->create();

    actingAs(orderViewer())
        ->get("/admin/orders/{$order->id}")
        ->assertOk();
});

it('allows a legal status transition and audit-logs it', function () {
    $order = Order::factory()->create(['status' => 'pending']);

    actingAs(orderManagerUser())
        ->put("/admin/orders/{$order->id}/status", ['status' => 'confirmed'])
        ->assertRedirect();

    expect($order->refresh()->status)->toBe('confirmed');
    expect(Activity::query()->where('log_name', 'Order')->exists())->toBeTrue();
});

it('rejects an illegal status transition', function () {
    $order = Order::factory()->create(['status' => 'pending']);

    actingAs(orderManagerUser())
        ->put("/admin/orders/{$order->id}/status", ['status' => 'shipped'])
        ->assertSessionHasErrors('status');

    expect($order->refresh()->status)->toBe('pending');
});

it('blocks orders.view-only staff from changing status', function () {
    $order = Order::factory()->create(['status' => 'pending']);

    actingAs(orderViewer())
        ->put("/admin/orders/{$order->id}/status", ['status' => 'confirmed'])
        ->assertForbidden();
});
