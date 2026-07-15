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
 * An order-level discount an admin grants after placement. It moves the total,
 * hence the due and the amount the pay link + invoice charge — so it is guarded
 * on paid / booked / over-discount / below-paid edges, and every downstream
 * surface (here: PaymentAmount) reads the recomputed order row.
 */
beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager'); // orders.view + orders.manage
});

/** subtotal ৳10,000 + shipping ৳100 = total ৳10,100, unpaid, no shipment. */
function discountableOrder(array $overrides = []): Order
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
        'order_id' => $order->id,
        'product_id' => $product->id,
        'price' => Money::fromMinor(1000000),
        'qty' => 1,
        'line_total' => Money::fromMinor(1000000),
    ]);

    return $order->fresh(['items']);
}

it('reduces the total and the due', function () {
    $order = discountableOrder();

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/discount", ['discount' => 500, 'note' => 'Loyal customer'])
        ->assertRedirect();

    $order->refresh();

    expect($order->discount->toMinor())->toBe(50000)          // ৳500
        ->and($order->total->toMinor())->toBe(960000)          // 10,000 − 500 + 100
        ->and($order->discount_note)->toBe('Loyal customer')
        ->and($order->discount_by)->toBe($this->manager->id);
});

it('makes the pay link charge the reduced amount', function () {
    $order = discountableOrder();

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/discount", ['discount' => 500, 'note' => 'Loyal customer']);

    // SSLCommerz reads orders.total live through PaymentAmount — no separate change.
    expect(PaymentAmount::for($order->refresh(), Payment::TYPE_FULL)->toMinor())->toBe(960000);
});

it('restores the total when the discount is cleared', function () {
    $order = discountableOrder([
        'discount' => Money::fromMinor(50000),
        'total' => Money::fromMinor(960000),
        'discount_note' => 'old',
    ]);

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/discount", ['discount' => 0]);

    $order->refresh();

    expect($order->discount->toMinor())->toBe(0)
        ->and($order->total->toMinor())->toBe(1010000)
        ->and($order->discount_note)->toBeNull();
});

it('replaces an existing discount rather than stacking it', function () {
    $order = discountableOrder([
        'discount' => Money::fromMinor(50000),
        'total' => Money::fromMinor(960000),
    ]);

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/discount", ['discount' => 800, 'note' => 'bigger']);

    $order->refresh();

    expect($order->discount->toMinor())->toBe(80000)      // 800, not 1,300
        ->and($order->total->toMinor())->toBe(930000);     // 10,000 − 800 + 100
});

it('rejects a discount greater than the subtotal', function () {
    $order = discountableOrder();

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/discount", ['discount' => 10001, 'note' => 'too much'])
        ->assertSessionHasErrors('discount');

    expect($order->refresh()->total->toMinor())->toBe(1010000);
});

it('rejects a discount on a paid order', function () {
    $order = discountableOrder([
        'payment_status' => 'paid',
        'advance_paid' => Money::fromMinor(1010000),
    ]);

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/discount", ['discount' => 500, 'note' => 'nope'])
        ->assertSessionHasErrors('discount');

    expect($order->refresh()->discount->toMinor())->toBe(0);
});

it('blocks a discount when a consignment is already booked', function () {
    $order = discountableOrder();
    Shipment::factory()->create(['order_id' => $order->id]);

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/discount", ['discount' => 500, 'note' => 'nope'])
        ->assertSessionHasErrors('discount');

    expect($order->refresh()->total->toMinor())->toBe(1010000);
});

it('rejects a discount that would drop the total below what was paid', function () {
    $order = discountableOrder([
        'advance_paid' => Money::fromMinor(970000), // ৳9,700 collected
        'payment_status' => 'partial',
    ]);

    // ৳500 off → total ৳9,600, below the ৳9,700 already paid.
    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/discount", ['discount' => 500, 'note' => 'nope'])
        ->assertSessionHasErrors('discount');

    expect($order->refresh()->total->toMinor())->toBe(1010000);
});

it('reconciles a partially-paid order after a discount', function () {
    $order = discountableOrder(['advance_paid' => Money::fromMinor(300000), 'payment_status' => 'partial']);
    // A successful credit so the reconciler can recompute paid/partial/unpaid.
    Payment::factory()->create([
        'order_id' => $order->id,
        'amount' => Money::fromMinor(300000),
        'direction' => Payment::DIRECTION_CREDIT,
        'status' => Payment::STATUS_SUCCESS,
    ]);

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/discount", ['discount' => 500, 'note' => 'goodwill']);

    $order->refresh();
    $dueMinor = $order->total->toMinor() - $order->advance_paid->toMinor();

    expect($order->total->toMinor())->toBe(960000)     // ৳9,600
        ->and($order->advance_paid->toMinor())->toBe(300000)
        ->and($dueMinor)->toBe(660000)                  // ৳6,600
        ->and($order->payment_status)->toBe('partial');
});

it('requires a note for a non-zero discount', function () {
    $order = discountableOrder();

    actingAs($this->manager)
        ->put("/admin/orders/{$order->id}/discount", ['discount' => 500])
        ->assertSessionHasErrors('note');
});

it('forbids applying a discount without orders.manage', function () {
    $order = discountableOrder();
    $user = User::factory()->create();
    $user->assignRole('marketer');

    actingAs($user)
        ->put("/admin/orders/{$order->id}/discount", ['discount' => 500, 'note' => 'x'])
        ->assertForbidden();
});
