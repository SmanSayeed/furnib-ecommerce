<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use App\Support\Money;
use App\Support\Payments\PaymentAmount;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

/**
 * A manual delivery-charge override on an existing order. Shipping is normally
 * derived from the zone, but a specific order sometimes needs a hand-set figure.
 * Because it moves the total it is guarded exactly like a discount — paid /
 * booked / below-paid — and the pay link + invoice follow the recomputed row.
 */
beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager'); // orders.manage
});

/** subtotal ৳10,000 + shipping ৳100 = total ৳10,100, unpaid, no shipment. */
function shippableOrder(array $overrides = []): Order
{
    $product = Product::factory()->create(['price' => 1000, 'stock_amount' => 20, 'stock_status' => true]);

    $order = Order::factory()->create(array_merge([
        'subtotal' => Money::fromMinor(1000000),
        'discount' => Money::fromMinor(0),
        'shipping_cost' => Money::fromMinor(10000),
        'total' => Money::fromMinor(1010000),
        'advance_paid' => Money::fromMinor(0),
        'payment_status' => 'unpaid',
    ], $overrides));

    OrderItem::factory()->create([
        'order_id' => $order->id, 'product_id' => $product->id,
        'price' => Money::fromMinor(1000000), 'qty' => 1, 'line_total' => Money::fromMinor(1000000),
    ]);

    return $order->fresh(['items']);
}

it('overrides the delivery charge and recomputes the total', function () {
    $order = shippableOrder();

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/shipping", ['shipping_cost' => 250])
        ->assertRedirect();

    $order->refresh();

    expect($order->shipping_cost->toMinor())->toBe(25000)     // ৳250
        ->and($order->total->toMinor())->toBe(1025000);        // 10,000 + 250
});

it('can set free delivery (zero)', function () {
    $order = shippableOrder();

    actingAs($this->manager)->put("/admin/orders/{$order->id}/shipping", ['shipping_cost' => 0]);

    $order->refresh();
    expect($order->shipping_cost->toMinor())->toBe(0)
        ->and($order->total->toMinor())->toBe(1000000);        // subtotal only
});

it('keeps an order-level discount when overriding shipping', function () {
    $order = shippableOrder(['discount' => Money::fromMinor(50000), 'total' => Money::fromMinor(960000)]);

    actingAs($this->manager)->put("/admin/orders/{$order->id}/shipping", ['shipping_cost' => 200]);

    // total = subtotal 10,000 − discount 500 + shipping 200 = 9,700
    expect($order->refresh()->total->toMinor())->toBe(970000);
});

it('makes the pay link charge the new total', function () {
    $order = shippableOrder();

    actingAs($this->manager)->put("/admin/orders/{$order->id}/shipping", ['shipping_cost' => 250]);

    expect(PaymentAmount::for($order->refresh(), Payment::TYPE_FULL)->toMinor())->toBe(1025000);
});

it('rejects an override on a paid order', function () {
    $order = shippableOrder(['payment_status' => 'paid', 'advance_paid' => Money::fromMinor(1010000)]);

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/shipping", ['shipping_cost' => 250])
        ->assertSessionHasErrors('shipping_cost');

    expect($order->refresh()->shipping_cost->toMinor())->toBe(10000);
});

it('blocks an override when a consignment is already booked', function () {
    $order = shippableOrder();
    Shipment::factory()->create(['order_id' => $order->id]);

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/shipping", ['shipping_cost' => 250])
        ->assertSessionHasErrors('shipping_cost');

    expect($order->refresh()->shipping_cost->toMinor())->toBe(10000);
});

it('rejects an override that drops the total below what was paid', function () {
    $order = shippableOrder([
        'advance_paid' => Money::fromMinor(1005000), // ৳10,050 collected
        'payment_status' => 'partial',
    ]);

    // shipping 0 → total 10,000, below the 10,050 already paid.
    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/shipping", ['shipping_cost' => 0])
        ->assertSessionHasErrors('shipping_cost');

    expect($order->refresh()->total->toMinor())->toBe(1010000);
});

it('reconciles a partially-paid order after the override', function () {
    $order = shippableOrder(['advance_paid' => Money::fromMinor(300000), 'payment_status' => 'partial']);
    Payment::factory()->create([
        'order_id' => $order->id, 'amount' => Money::fromMinor(300000),
        'direction' => Payment::DIRECTION_CREDIT, 'status' => Payment::STATUS_SUCCESS,
    ]);

    actingAs($this->manager)->put("/admin/orders/{$order->id}/shipping", ['shipping_cost' => 500]);

    $order->refresh();
    expect($order->total->toMinor())->toBe(1050000)             // 10,000 + 500
        ->and($order->advance_paid->toMinor())->toBe(300000)
        ->and($order->payment_status)->toBe('partial');
});

it('forbids overriding shipping without orders.manage', function () {
    $order = shippableOrder();
    $user = User::factory()->create();
    $user->assignRole('marketer');

    actingAs($user)
        ->put("/admin/orders/{$order->id}/shipping", ['shipping_cost' => 250])
        ->assertForbidden();
});
