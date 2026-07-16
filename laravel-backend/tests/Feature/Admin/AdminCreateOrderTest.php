<?php

declare(strict_types=1);

use App\Actions\Orders\PlaceOrder;
use App\DTOs\PlaceOrderData;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

/**
 * Staff placing an order on a customer's behalf. It reuses the storefront
 * placement engine but unlocks the staff-only levers (unit price / discount /
 * shipping override) via source = admin — a gate that MUST stay closed for the
 * public checkout, which is the one regression this file guards hardest.
 */
beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager'); // orders.manage
});

function sellable(array $overrides = []): Product
{
    return Product::factory()->create(array_merge([
        'price' => 1000, 'discount_price' => null,
        'stock_amount' => 10, 'stock_status' => true,
        'product_status' => 'published',
        'shipping_charge_allowed' => false,
    ], $overrides));
}

function createPayload(Product $product, array $overrides = []): array
{
    return array_merge([
        'customer' => ['name' => 'Rahim', 'mobile' => '01712345678', 'email' => null],
        'address' => 'House 1, Dhaka',
        'shipping_zone_id' => null,
        'items' => [['product_id' => $product->id, 'qty' => 2]],
    ], $overrides);
}

it('creates an admin order at the effective price and decrements stock', function () {
    $product = sellable();

    actingAs($this->manager)
        ->post('/admin/orders', createPayload($product))
        ->assertRedirect();

    $order = Order::query()->latest('id')->firstOrFail();

    expect($order->source)->toBe('admin')
        ->and($order->created_by)->toBe($this->manager->id)
        ->and($order->subtotal->toMinor())->toBe(200000)      // 2 × ৳1,000
        ->and($order->total->toMinor())->toBe(200000)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->price->toMinor())->toBe(100000)
        ->and($product->refresh()->stock_amount)->toBe(8);     // 10 − 2
});

it('honours a per-line unit price override for an admin order', function () {
    $product = sellable(['price' => 1000]);

    actingAs($this->manager)
        ->post('/admin/orders', createPayload($product, [
            'items' => [['product_id' => $product->id, 'qty' => 2, 'unit_price' => 800]],
        ]))
        ->assertRedirect();

    $order = Order::query()->latest('id')->firstOrFail();
    $line = $order->items->first();

    expect($line->price->toMinor())->toBe(80000)               // overridden ৳800
        ->and($order->subtotal->toMinor())->toBe(160000)
        // Charged below the regular ৳1,000 → the saving is snapshotted.
        ->and($line->original_price?->toMinor())->toBe(100000)
        ->and($line->discount_amount->toMinor())->toBe(40000); // (1000−800)×2
});

it('IGNORES a price override on a storefront order (price-tampering guard)', function () {
    // The critical security property: even if a storefront payload smuggles a
    // price_override, PlaceOrder must charge the real effective price because the
    // source is not admin.
    $product = sellable(['price' => 1000]);

    $order = app(PlaceOrder::class)->handle(new PlaceOrderData(
        items: [['product_id' => $product->id, 'qty' => 1, 'price_override' => 1]], // ৳0.01
        customerMobile: '+8801712345678',
        customerName: 'Hacker',
        customerEmail: null,
        shippingZoneId: null,
        address: 'Dhaka',
        source: 'storefront',
    ));

    expect($order->items->first()->price->toMinor())->toBe(100000); // full ৳1,000, not ৳0.01
});

it('applies an order-level discount at creation', function () {
    $product = sellable(['price' => 1000]);

    actingAs($this->manager)
        ->post('/admin/orders', createPayload($product, ['discount' => 300, 'discount_note' => 'Bulk deal']))
        ->assertRedirect();

    $order = Order::query()->latest('id')->firstOrFail();

    expect($order->discount->toMinor())->toBe(30000)
        ->and($order->total->toMinor())->toBe(170000)          // 2,000 − 300
        ->and($order->discount_note)->toBe('Bulk deal')
        ->and($order->discount_by)->toBe($this->manager->id);
});

it('applies a manual shipping override', function () {
    $product = sellable();
    $zone = ShippingZone::factory()->create(['cost' => 80]);

    actingAs($this->manager)
        ->post('/admin/orders', createPayload($product, [
            'shipping_zone_id' => $zone->id,
            'shipping_override' => 150,
        ]))
        ->assertRedirect();

    $order = Order::query()->latest('id')->firstOrFail();

    expect($order->shipping_cost->toMinor())->toBe(15000)      // overridden ৳150, not ৳80
        ->and($order->total->toMinor())->toBe(215000);
});

it('records an advance as a manual payment and derives the payment status', function () {
    $product = sellable(['price' => 1000]);

    actingAs($this->manager)
        ->post('/admin/orders', createPayload($product, [
            'advance_paid' => 500,
            'advance_method' => 'bkash',
            'advance_note' => 'TrxID ABC123',
        ]))
        ->assertRedirect();

    $order = Order::query()->latest('id')->firstOrFail();
    $payment = $order->payments->first();

    expect($order->advance_paid->toMinor())->toBe(50000)
        ->and($order->payment_status)->toBe('partial')
        ->and($order->payments)->toHaveCount(1)
        ->and($payment->method)->toBe('bkash')
        ->and($payment->note)->toBe('TrxID ABC123');
});

it('requires a method when an advance is collected at creation', function () {
    $product = sellable();

    actingAs($this->manager)
        ->post('/admin/orders', createPayload($product, ['advance_paid' => 500]))
        ->assertSessionHasErrors('advance_method');
});

it('confirms the order immediately when asked', function () {
    $product = sellable();

    actingAs($this->manager)
        ->post('/admin/orders', createPayload($product, ['confirm' => true]))
        ->assertRedirect();

    expect(Order::query()->latest('id')->firstOrFail()->status)->toBe('confirmed');
});

it('reuses an existing customer resolved by mobile', function () {
    $product = sellable();

    actingAs($this->manager)->post('/admin/orders', createPayload($product));
    actingAs($this->manager)->post('/admin/orders', createPayload($product));

    // Both orders resolved to the same customer (find-or-create by mobile).
    $orders = Order::query()->latest('id')->take(2)->get();
    expect($orders[0]->customer_id)->toBe($orders[1]->customer_id);
});

it('rejects an order with no items', function () {
    actingAs($this->manager)
        ->post('/admin/orders', [
            'customer' => ['mobile' => '01712345678'],
            'address' => 'Dhaka',
            'items' => [],
        ])
        ->assertSessionHasErrors('items');
});

it('forbids creating an order without orders.manage', function () {
    $product = sellable();
    $user = User::factory()->create();
    $user->assignRole('marketer');

    actingAs($user)
        ->post('/admin/orders', createPayload($product))
        ->assertForbidden();
});

it('searches products for the picker at the effective price', function () {
    sellable(['title' => 'Tulip Chair', 'sku' => 'CHAIR-1', 'price' => 1000, 'discount_price' => 800]);

    $data = actingAs($this->manager)
        ->getJson('/admin/orders/product-search?q=Tulip')
        ->assertOk()
        ->json('products');

    expect($data)->toHaveCount(1)
        ->and($data[0]['unit_price_minor'])->toBe(80000)       // discounted
        ->and($data[0]['is_discounted'])->toBeTrue();
});
