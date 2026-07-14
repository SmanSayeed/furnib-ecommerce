<?php

declare(strict_types=1);

use App\Actions\Orders\PlaceOrder;
use App\DTOs\PlaceOrderData;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductShippingCharge;
use App\Models\ShippingZone;
use App\Support\Money;
use App\Support\Payments\PaymentAmount;

/**
 * THE BUG: PlaceOrder resolved every line's unit price from `products.price` and
 * never looked at `products.discount_price`. A shopper saw ৳8,000 on the product
 * page and SSLCommerz asked for ৳10,000, because the wrong number was baked into
 * `orders.total` at placement and every downstream surface (invoice, SMS, pay
 * link, gateway) faithfully repeated it.
 *
 * These tests pin the discounted price all the way from the line snapshot to the
 * gateway payable.
 */
beforeEach(function () {
    $this->action = app(PlaceOrder::class);
});

function discountOrder(array $items, ?int $zoneId = null): PlaceOrderData
{
    return new PlaceOrderData(
        items: $items,
        customerMobile: '01712345678',
        customerName: 'Karim',
        customerEmail: null,
        shippingZoneId: $zoneId,
        address: 'House 1, Road 2, Dhaka',
        ip: '203.0.113.7',
        userAgent: 'PestAgent/1.0',
    );
}

it('charges the discounted price, not the regular price', function () {
    $product = Product::factory()->create([
        'price' => 10000, 'discount_price' => 8000,
        'stock_amount' => 10, 'stock_status' => true,
    ]);

    $order = $this->action->handle(discountOrder([['product_id' => $product->id, 'qty' => 1]]));

    expect($order->items->first()->price->toMinor())->toBe(800000)
        ->and($order->subtotal->toMinor())->toBe(800000)
        ->and($order->total->toMinor())->toBe(800000);
});

it('multiplies the discounted price by the quantity', function () {
    $product = Product::factory()->create([
        'price' => 10000, 'discount_price' => 8000,
        'stock_amount' => 10, 'stock_status' => true,
    ]);

    $order = $this->action->handle(discountOrder([['product_id' => $product->id, 'qty' => 3]]));

    expect($order->items->first()->line_total->toMinor())->toBe(2400000) // 8000 × 3
        ->and($order->subtotal->toMinor())->toBe(2400000);
});

it('snapshots the original price and the saving on a discounted line', function () {
    $product = Product::factory()->create([
        'price' => 10000, 'discount_price' => 8000,
        'stock_amount' => 10, 'stock_status' => true,
    ]);

    $order = $this->action->handle(discountOrder([['product_id' => $product->id, 'qty' => 2]]));
    $item = $order->items->first();

    expect($item->price->toMinor())->toBe(800000)
        ->and($item->original_price?->toMinor())->toBe(1000000)
        ->and($item->discount_amount->toMinor())->toBe(400000)   // (10000 − 8000) × 2
        ->and($item->line_total->toMinor())->toBe(1600000);
});

it('records no saving on an undiscounted line', function () {
    $product = Product::factory()->create([
        'price' => 1000, 'discount_price' => null,
        'stock_amount' => 10, 'stock_status' => true,
    ]);

    $item = $this->action->handle(discountOrder([['product_id' => $product->id, 'qty' => 1]]))->items->first();

    expect($item->original_price)->toBeNull()
        ->and($item->discount_amount->toMinor())->toBe(0);
});

it('resolves each line of a mixed cart independently', function () {
    $discounted = Product::factory()->create([
        'price' => 10000, 'discount_price' => 8000, 'stock_amount' => 10, 'stock_status' => true,
    ]);
    $regular = Product::factory()->create([
        'price' => 500, 'discount_price' => null, 'stock_amount' => 10, 'stock_status' => true,
    ]);

    $order = $this->action->handle(discountOrder([
        ['product_id' => $discounted->id, 'qty' => 1],
        ['product_id' => $regular->id, 'qty' => 2],
    ]));

    expect($order->subtotal->toMinor())->toBe(900000); // 8000 + 500×2
});

it('ignores a discount that is not below the regular price', function () {
    // Legacy row that the (previously unguarded) API update endpoint could create.
    $product = Product::factory()->create([
        'price' => 1000, 'discount_price' => 1200,
        'stock_amount' => 10, 'stock_status' => true,
    ]);

    $order = $this->action->handle(discountOrder([['product_id' => $product->id, 'qty' => 1]]));

    expect($order->subtotal->toMinor())->toBe(100000)          // the price, never raised
        ->and($order->items->first()->original_price)->toBeNull();
});

it('charges nothing for a product deliberately discounted to zero', function () {
    $product = Product::factory()->create([
        'price' => 1000, 'discount_price' => 0,
        'stock_amount' => 10, 'stock_status' => true,
    ]);

    $order = $this->action->handle(discountOrder([['product_id' => $product->id, 'qty' => 1]]));

    expect($order->subtotal->toMinor())->toBe(0)
        ->and($order->total->toMinor())->toBe(0);
});

it('leaves shipping untouched by a discount', function () {
    $product = Product::factory()->create([
        'price' => 10000, 'discount_price' => 8000, 'stock_amount' => 10, 'stock_status' => true,
    ]);
    $zone = ShippingZone::factory()->create(['cost' => 80]);
    ProductShippingCharge::factory()->create([
        'product_id' => $product->id,
        'shipping_zone_id' => $zone->id,
        'extra_cost' => Money::fromMinor(2000), // ৳20/unit
    ]);

    $order = $this->action->handle(discountOrder([['product_id' => $product->id, 'qty' => 2]], $zone->id));

    expect($order->shipping_cost->toMinor())->toBe(12000)       // 80 + 20×2, unchanged
        ->and($order->subtotal->toMinor())->toBe(1600000)       // 8000 × 2
        ->and($order->total->toMinor())->toBe(1612000);
});

it('keeps the discounted snapshot when the discount is later removed', function () {
    $product = Product::factory()->create([
        'price' => 10000, 'discount_price' => 8000, 'stock_amount' => 10, 'stock_status' => true,
    ]);

    $order = $this->action->handle(discountOrder([['product_id' => $product->id, 'qty' => 1]]));
    $product->update(['discount_price' => null]);

    expect($order->items->first()->price->toMinor())->toBe(800000);
});

it('computes a percentage advance from the discounted line total', function () {
    // 30% of the ৳8,000 discounted line = ৳2,400 — NOT 30% of ৳10,000 (৳3,000).
    $product = Product::factory()->create([
        'price' => 10000, 'discount_price' => 8000, 'stock_amount' => 10, 'stock_status' => true,
        'is_advance_payment' => true, 'advance_payment_type' => 'partial',
        'partial_amount_type' => 'percentage', 'partial_amount' => 30,
    ]);

    $order = $this->action->handle(discountOrder([['product_id' => $product->id, 'qty' => 1]]));

    expect($order->advance_amount->toMinor())->toBe(240000);
});

it('caps a fixed advance at the discounted line total', function () {
    // ৳9,000 fixed advance on an ৳8,000 discounted line → ৳8,000, never ৳9,000.
    $product = Product::factory()->create([
        'price' => 10000, 'discount_price' => 8000, 'stock_amount' => 10, 'stock_status' => true,
        'is_advance_payment' => true, 'advance_payment_type' => 'partial',
        'partial_amount_type' => 'amount', 'partial_amount' => 900000, // paisa
    ]);

    $order = $this->action->handle(discountOrder([['product_id' => $product->id, 'qty' => 1]]));

    expect($order->advance_amount->toMinor())->toBe(800000);
});

it('sets a full advance to the discounted subtotal plus shipping', function () {
    $product = Product::factory()->create([
        'price' => 10000, 'discount_price' => 8000, 'stock_amount' => 10, 'stock_status' => true,
        'is_advance_payment' => true, 'advance_payment_type' => 'full',
    ]);
    $zone = ShippingZone::factory()->create(['cost' => 80]);

    $order = $this->action->handle(discountOrder([['product_id' => $product->id, 'qty' => 1]], $zone->id));

    expect($order->advance_amount->toMinor())->toBe(808000)     // 8000 + 80
        ->and($order->advance_amount->toMinor())->toBe($order->total->toMinor());
});

it('shows the saving on the invoice with a subtotal that still adds up', function () {
    $product = Product::factory()->create([
        'price' => 10000, 'discount_price' => 8000, 'stock_amount' => 10, 'stock_status' => true,
    ]);
    $zone = ShippingZone::factory()->create(['cost' => 80]);

    $order = $this->action->handle(discountOrder([['product_id' => $product->id, 'qty' => 2]], $zone->id));

    $html = view('invoices.order', [
        'order' => $order->load('items', 'customer', 'shippingZone'),
        'siteName' => 'Furnib',
        'logoUrl' => null,
        'company' => ['name' => 'Furnib', 'website' => null, 'address' => null, 'phone' => null, 'email' => null],
    ])->render();

    // gross 20,000 − discount 4,000 + delivery 80 = total 16,080. The column adds up.
    expect($html)->toContain('20,000Tk.')   // gross subtotal
        ->toContain('4,000Tk.')             // discount
        ->toContain('16,080Tk.');           // total
});

it('charges the gateway the discounted total', function () {
    $product = Product::factory()->create([
        'price' => 10000, 'discount_price' => 8000, 'stock_amount' => 10, 'stock_status' => true,
    ]);

    $order = $this->action->handle(discountOrder([['product_id' => $product->id, 'qty' => 1]]));

    expect(PaymentAmount::for($order, Payment::TYPE_FULL)->toMinor())->toBe(800000);
});
