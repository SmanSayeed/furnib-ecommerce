<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function ordersViewer(): User
{
    $user = User::factory()->create();
    $user->assignRole('sub-admin'); // orders.view

    return $user;
}

it('denies the order list to users without orders.view', function () {
    actingAs(User::factory()->create())
        ->get('/admin/orders')
        ->assertForbidden();
});

it('searches orders by customer mobile', function () {
    $match = Customer::factory()->create(['mobile' => '01711000111']);
    $other = Customer::factory()->create(['mobile' => '01999888777']);
    Order::factory()->create(['customer_id' => $match->id]);
    Order::factory()->create(['customer_id' => $other->id]);

    actingAs(ordersViewer())
        ->get('/admin/orders?search=01711')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('orders/index')
            ->has('orders', 1)
            ->where('orders.0.mobile', '01711000111'));
});

it('filters orders by payment status', function () {
    Order::factory()->create(['payment_status' => 'partial']);
    Order::factory()->create(['payment_status' => 'unpaid']);
    Order::factory()->create(['payment_status' => 'paid']);

    actingAs(ordersViewer())
        ->get('/admin/orders?payment_status=partial')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders', 1)
            ->where('orders.0.payment_status', 'partial'));
});

it('sorts orders by total descending', function () {
    $cheap = Order::factory()->create(['subtotal' => 100, 'shipping_cost' => 0, 'total' => 100]);
    $pricey = Order::factory()->create(['subtotal' => 9000, 'shipping_cost' => 0, 'total' => 9000]);

    actingAs(ordersViewer())
        ->get('/admin/orders?sort=total&dir=desc')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders.0.order_no', $pricey->order_no)
            ->where('orders.1.order_no', $cheap->order_no));
});

it('limits the order list to today with the today preset', function () {
    Order::factory()->create(['created_at' => Carbon::now()]);
    Order::factory()->create(['created_at' => Carbon::now()->subDays(10)]);

    actingAs(ordersViewer())
        ->get('/admin/orders?range=today')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('orders', 1));
});

it('rejects a non-whitelisted sort and falls back to the default', function () {
    Order::factory()->count(2)->create();

    actingAs(ordersViewer())
        ->get('/admin/orders?sort=customer_ip')
        ->assertOk(); // no SQL error: unknown sort is dropped to created_at
});
