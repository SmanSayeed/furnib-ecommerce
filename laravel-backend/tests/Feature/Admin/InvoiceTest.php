<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

it('downloads an order invoice as a PDF for orders.view staff', function () {
    $order = Order::factory()->has(OrderItem::factory()->count(2), 'items')->create();
    $user = User::factory()->create();
    $user->assignRole('sub-admin'); // orders.view

    $response = actingAs($user)->get("/admin/orders/{$order->id}/invoice");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain($order->order_no);
});

it('blocks staff without orders.view from the invoice', function () {
    $order = Order::factory()->create();
    $user = User::factory()->create();
    $user->assignRole('marketer'); // no orders.view

    actingAs($user)
        ->get("/admin/orders/{$order->id}/invoice")
        ->assertForbidden();
});

it('returns 404 for a missing order invoice', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    actingAs($user)
        ->get('/admin/orders/999999/invoice')
        ->assertNotFound();
});
