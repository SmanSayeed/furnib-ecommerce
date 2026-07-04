<?php

declare(strict_types=1);

use App\Actions\Orders\PlaceOrder;
use App\DTOs\PlaceOrderData;
use App\Models\Product;
use App\Models\ProductShippingCharge;
use App\Models\ShippingZone;
use App\Support\Money;

beforeEach(function () {
    $this->action = app(PlaceOrder::class);
});

function freeShipData(array $items, array $overrides = []): PlaceOrderData
{
    return new PlaceOrderData(
        items: $items,
        customerMobile: '01712345678',
        customerName: 'Karim',
        customerEmail: null,
        shippingZoneId: $overrides['shipping_zone_id'] ?? null,
        address: 'House 1, Road 2, Dhaka',
        ip: '203.0.113.7',
        userAgent: 'PestAgent/1.0',
        notes: null,
    );
}

it('charges zero shipping for a free-shipping product even with a zone selected', function () {
    $product = Product::factory()->create([
        'price' => 1000, 'stock_amount' => 10, 'stock_status' => true,
        'shipping_charge_allowed' => false,
    ]);
    $zone = ShippingZone::factory()->create(['cost' => 80]); // base ৳80

    $order = $this->action->handle(freeShipData(
        [['product_id' => $product->id, 'qty' => 2]],
        ['shipping_zone_id' => $zone->id],
    ));

    expect($order->shipping_cost->toMinor())->toBe(0)      // no base, no extra
        ->and($order->total->toMinor())->toBe(200000);      // subtotal only
});

it('ignores any per-zone extra rows on a free-shipping product', function () {
    $product = Product::factory()->create([
        'price' => 1000, 'stock_amount' => 10, 'stock_status' => true,
        'shipping_charge_allowed' => false,
    ]);
    $zone = ShippingZone::factory()->create(['cost' => 80]);
    // A stale extra row must never be applied once shipping is disabled.
    ProductShippingCharge::factory()->create([
        'product_id' => $product->id,
        'shipping_zone_id' => $zone->id,
        'extra_cost' => Money::fromMinor(5000),
    ]);

    $order = $this->action->handle(freeShipData(
        [['product_id' => $product->id, 'qty' => 3]],
        ['shipping_zone_id' => $zone->id],
    ));

    expect($order->shipping_cost->toMinor())->toBe(0);
});

it('applies the zone base once for a mixed cart, free items adding nothing', function () {
    $paid = Product::factory()->create([
        'price' => 1000, 'stock_amount' => 10, 'stock_status' => true,
        'shipping_charge_allowed' => true,
    ]);
    $free = Product::factory()->create([
        'price' => 500, 'stock_amount' => 10, 'stock_status' => true,
        'shipping_charge_allowed' => false,
    ]);
    $zone = ShippingZone::factory()->create(['cost' => 80]);
    ProductShippingCharge::factory()->create([
        'product_id' => $paid->id,
        'shipping_zone_id' => $zone->id,
        'extra_cost' => Money::fromMinor(2000), // ৳20/unit on the paid item
    ]);

    $order = $this->action->handle(freeShipData(
        [
            ['product_id' => $paid->id, 'qty' => 2],
            ['product_id' => $free->id, 'qty' => 3],
        ],
        ['shipping_zone_id' => $zone->id],
    ));

    // 80 base + 20×2 (paid) + 0 (free) = 120.00
    expect($order->shipping_cost->toMinor())->toBe(12000);
});

it('ships free when every item in the cart is free-shipping', function () {
    $a = Product::factory()->create([
        'price' => 1000, 'stock_amount' => 10, 'stock_status' => true,
        'shipping_charge_allowed' => false,
    ]);
    $b = Product::factory()->create([
        'price' => 500, 'stock_amount' => 10, 'stock_status' => true,
        'shipping_charge_allowed' => false,
    ]);
    $zone = ShippingZone::factory()->create(['cost' => 80]);

    $order = $this->action->handle(freeShipData(
        [
            ['product_id' => $a->id, 'qty' => 1],
            ['product_id' => $b->id, 'qty' => 1],
        ],
        ['shipping_zone_id' => $zone->id],
    ));

    // No chargeable line → the zone base is not added at all.
    expect($order->shipping_cost->toMinor())->toBe(0)
        ->and($order->total->toMinor())->toBe(150000);
});

it('does not force a delivery area for a free-shipping shipping-advance product', function () {
    // Contradictory config (free shipping + advance-as-shipping): the product has
    // no shipping to prepay, so it must neither require a zone nor add an advance.
    $product = Product::factory()->create([
        'price' => 1000, 'stock_amount' => 10, 'stock_status' => true,
        'is_advance_payment' => true, 'advance_payment_type' => 'partial',
        'partial_amount_type' => 'shipping', 'partial_amount' => null,
        'shipping_charge_allowed' => false,
    ]);

    $order = $this->action->handle(freeShipData([['product_id' => $product->id, 'qty' => 1]]));

    expect($order->shipping_cost->toMinor())->toBe(0)
        ->and($order->advance_amount->toMinor())->toBe(0);
});

it('sets a full advance to the subtotal when the product ships free', function () {
    $product = Product::factory()->create([
        'price' => 1000, 'stock_amount' => 10, 'stock_status' => true,
        'is_advance_payment' => true, 'advance_payment_type' => 'full',
        'shipping_charge_allowed' => false,
    ]);
    $zone = ShippingZone::factory()->create(['cost' => 80]);

    $order = $this->action->handle(freeShipData(
        [['product_id' => $product->id, 'qty' => 2]],
        ['shipping_zone_id' => $zone->id],
    ));

    // Shipping is 0, so the full advance equals the subtotal (= total).
    expect($order->shipping_cost->toMinor())->toBe(0)
        ->and($order->advance_amount->toMinor())->toBe(200000)
        ->and($order->advance_amount->toMinor())->toBe($order->total->toMinor());
});
