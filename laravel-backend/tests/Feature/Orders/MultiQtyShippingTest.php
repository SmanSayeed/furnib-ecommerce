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
 * Cheaper delivery for each unit AFTER the first.
 *
 *   enabled + a rate for the zone → extra + multi × (qty − 1)
 *   otherwise                     → extra × qty          (today's behaviour)
 *
 *   shipping = zone base (once per ORDER) + Σ over lines
 *
 * A chair Inside Dhaka (base ৳80, extra ৳20, additional ৳10):
 *   qty 1 → 80 + 20        = ৳100
 *   qty 2 → 80 + 20 + 10   = ৳110
 *   qty 3 → 80 + 20 + 10×2 = ৳120     (was ৳140: 80 + 20×3)
 */
beforeEach(function () {
    $this->action = app(PlaceOrder::class);
});

function multiQtyOrder(array $items, ?int $zoneId = null): PlaceOrderData
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

/** A chair with an Inside-Dhaka extra of ৳20 and an additional-unit rate of ৳10. */
function chairWithTier(ShippingZone $zone, bool $enabled = true, ?int $multiMinor = 1000): Product
{
    $chair = Product::factory()->create([
        'price' => 1000, 'stock_amount' => 20, 'stock_status' => true,
        'shipping_charge_allowed' => true,
        'multi_qty_shipping_enabled' => $enabled,
    ]);

    ProductShippingCharge::factory()->create([
        'product_id' => $chair->id,
        'shipping_zone_id' => $zone->id,
        'extra_cost' => Money::fromMinor(2000),                                  // ৳20 first unit
        'multi_extra_cost' => $multiMinor === null ? null : Money::fromMinor($multiMinor), // ৳10 each additional
    ]);

    return $chair;
}

it('charges the additional-unit rate for every unit after the first', function () {
    $zone = ShippingZone::factory()->create(['cost' => 80]);
    $chair = chairWithTier($zone);

    $order = $this->action->handle(multiQtyOrder([['product_id' => $chair->id, 'qty' => 3]], $zone->id));

    // 80 base + 20 (first) + 10 × 2 (additional) = ৳120
    expect($order->shipping_cost->toMinor())->toBe(12000);
});

it('leaves a single-unit order untouched by the option', function () {
    $zone = ShippingZone::factory()->create(['cost' => 80]);
    $chair = chairWithTier($zone);

    $order = $this->action->handle(multiQtyOrder([['product_id' => $chair->id, 'qty' => 1]], $zone->id));

    // (1 − 1) × multi = 0, so this is exactly the option-off amount.
    expect($order->shipping_cost->toMinor())->toBe(10000); // 80 + 20
});

it('keeps the per-unit behaviour when the option is off', function () {
    $zone = ShippingZone::factory()->create(['cost' => 80]);
    $chair = chairWithTier($zone, enabled: false);

    $order = $this->action->handle(multiQtyOrder([['product_id' => $chair->id, 'qty' => 3]], $zone->id));

    // Today's rule: 80 + 20 × 3 = ৳140.
    expect($order->shipping_cost->toMinor())->toBe(14000);
});

it('falls back to the per-unit extra when the zone has no additional-unit rate', function () {
    $zone = ShippingZone::factory()->create(['cost' => 80]);
    $chair = chairWithTier($zone, multiMinor: null);

    $order = $this->action->handle(multiQtyOrder([['product_id' => $chair->id, 'qty' => 3]], $zone->id));

    expect($order->shipping_cost->toMinor())->toBe(14000); // 80 + 20×3
});

it('treats a zero additional-unit rate as free for the later units', function () {
    // 0 is a deliberate value, not "unset" — later units ship for nothing.
    $zone = ShippingZone::factory()->create(['cost' => 80]);
    $chair = chairWithTier($zone, multiMinor: 0);

    $order = $this->action->handle(multiQtyOrder([['product_id' => $chair->id, 'qty' => 3]], $zone->id));

    expect($order->shipping_cost->toMinor())->toBe(10000); // 80 + 20 + 0×2
});

it('resolves each line independently and charges the zone base once', function () {
    $zone = ShippingZone::factory()->create(['cost' => 80]);
    $chair = chairWithTier($zone);

    $table = Product::factory()->create([
        'price' => 5000, 'stock_amount' => 10, 'stock_status' => true,
        'shipping_charge_allowed' => true,
        'multi_qty_shipping_enabled' => false,   // plain per-unit
    ]);
    ProductShippingCharge::factory()->create([
        'product_id' => $table->id,
        'shipping_zone_id' => $zone->id,
        'extra_cost' => Money::fromMinor(4000),  // ৳40/unit
        'multi_extra_cost' => null,
    ]);

    $order = $this->action->handle(multiQtyOrder([
        ['product_id' => $chair->id, 'qty' => 3],
        ['product_id' => $table->id, 'qty' => 1],
    ], $zone->id));

    // 80 (base, once) + [20 + 10×2] (chairs) + [40×1] (table) = ৳160
    expect($order->shipping_cost->toMinor())->toBe(16000);
});

it('ignores the tier for a free-shipping product', function () {
    $zone = ShippingZone::factory()->create(['cost' => 80]);
    $chair = chairWithTier($zone);
    $chair->update(['shipping_charge_allowed' => false]);

    $order = $this->action->handle(multiQtyOrder([['product_id' => $chair->id, 'qty' => 3]], $zone->id));

    // No chargeable line → the zone base isn't charged either.
    expect($order->shipping_cost->toMinor())->toBe(0);
});

it('carries the tiered shipping all the way to the gateway amount', function () {
    // The pay link, SSLCommerz, the invoice and the admin order all read
    // orders.total — so pinning the payable pins every one of them.
    $zone = ShippingZone::factory()->create(['cost' => 80]);
    $chair = chairWithTier($zone);

    $order = $this->action->handle(multiQtyOrder([['product_id' => $chair->id, 'qty' => 3]], $zone->id));

    $expected = 300000 + 12000; // 3 × ৳1,000 subtotal + ৳120 delivery

    expect($order->total->toMinor())->toBe($expected)
        ->and(PaymentAmount::for($order, Payment::TYPE_FULL)->toMinor())->toBe($expected);
});
