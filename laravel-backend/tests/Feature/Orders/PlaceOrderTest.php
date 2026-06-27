<?php

declare(strict_types=1);

use App\Actions\Orders\PlaceOrder;
use App\DTOs\PlaceOrderData;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductShippingCharge;
use App\Models\ShippingZone;
use App\Support\Money;
use App\Support\OrderNumber;

beforeEach(function () {
    $this->action = app(PlaceOrder::class);
});

function placeOrderData(array $items, array $overrides = []): PlaceOrderData
{
    return new PlaceOrderData(
        items: $items,
        customerMobile: $overrides['mobile'] ?? '01712345678',
        customerName: $overrides['name'] ?? 'Karim',
        customerEmail: $overrides['email'] ?? null,
        shippingZoneId: $overrides['shipping_zone_id'] ?? null,
        address: $overrides['address'] ?? 'House 1, Road 2, Dhaka',
        ip: $overrides['ip'] ?? '203.0.113.7',
        userAgent: $overrides['ua'] ?? 'PestAgent/1.0',
        notes: $overrides['notes'] ?? null,
    );
}

it('persists an order with snapshotted items and computes totals', function () {
    $product = Product::factory()->create([
        'price' => 1000, // 1000.00 taka
        'stock_amount' => 10,
        'stock_status' => true,
    ]);
    $zone = ShippingZone::factory()->create(['cost' => 80]);

    $order = $this->action->handle(placeOrderData(
        [['product_id' => $product->id, 'qty' => 2]],
        ['shipping_zone_id' => $zone->id],
    ));

    expect($order->items)->toHaveCount(1);
    expect($order->subtotal->toMinor())->toBe(200000);     // 1000 × 2
    expect($order->shipping_cost->toMinor())->toBe(8000);   // 80
    expect($order->total->toMinor())->toBe(208000);         // 2000 + 80
    expect($order->customer_ip)->toBe('203.0.113.7');
    expect($order->user_agent)->toBe('PestAgent/1.0');
    expect(OrderNumber::matchesFormat($order->order_no))->toBeTrue();
});

it('sets advance_amount to the selected delivery charge for a shipping-advance product', function () {
    $product = Product::factory()->create([
        'price' => 1000, 'stock_amount' => 10, 'stock_status' => true,
        'is_advance_payment' => true, 'advance_payment_type' => 'partial',
        'partial_amount_type' => 'shipping', 'partial_amount' => null,
    ]);
    $zone = ShippingZone::factory()->create(['cost' => 80]);

    $order = $this->action->handle(placeOrderData(
        [['product_id' => $product->id, 'qty' => 2]],
        ['shipping_zone_id' => $zone->id],
    ));

    expect($order->advance_amount->toMinor())->toBe(8000)   // = the zone's cost (80.00)
        ->and($order->shipping_cost->toMinor())->toBe(8000)
        ->and($order->total->toMinor())->toBe(208000);
});

it('adds the product per-unit extra shipping (x qty) on top of the zone base', function () {
    $product = Product::factory()->create([
        'price' => 1000, 'stock_amount' => 10, 'stock_status' => true,
    ]);
    $zone = ShippingZone::factory()->create(['cost' => 80]); // base ৳80
    ProductShippingCharge::factory()->create([
        'product_id' => $product->id,
        'shipping_zone_id' => $zone->id,
        'extra_cost' => Money::fromMinor(2000), // ৳20 per unit
    ]);

    $order = $this->action->handle(placeOrderData(
        [['product_id' => $product->id, 'qty' => 2]],
        ['shipping_zone_id' => $zone->id],
    ));

    // 80 base + 20 × 2 = 120.00
    expect($order->shipping_cost->toMinor())->toBe(12000)
        ->and($order->total->toMinor())->toBe(212000); // 2000 subtotal + 120
});

it('accumulates per-line extras and ignores products without a charge for the zone', function () {
    $table = Product::factory()->create(['price' => 1000, 'stock_amount' => 10, 'stock_status' => true]);
    $chair = Product::factory()->create(['price' => 500, 'stock_amount' => 10, 'stock_status' => true]);
    $zone = ShippingZone::factory()->create(['cost' => 80]);
    ProductShippingCharge::factory()->create([
        'product_id' => $table->id,
        'shipping_zone_id' => $zone->id,
        'extra_cost' => Money::fromMinor(2000), // ৳20/unit for the table only
    ]);

    $order = $this->action->handle(placeOrderData(
        [
            ['product_id' => $table->id, 'qty' => 2],
            ['product_id' => $chair->id, 'qty' => 1],
        ],
        ['shipping_zone_id' => $zone->id],
    ));

    // 80 + 20×2 (table) + 0×1 (chair) = 120.00
    expect($order->shipping_cost->toMinor())->toBe(12000);
});

it('prepays the full effective shipping for a shipping-advance product', function () {
    $product = Product::factory()->create([
        'price' => 1000, 'stock_amount' => 10, 'stock_status' => true,
        'is_advance_payment' => true, 'advance_payment_type' => 'partial',
        'partial_amount_type' => 'shipping', 'partial_amount' => null,
    ]);
    $zone = ShippingZone::factory()->create(['cost' => 80]);
    ProductShippingCharge::factory()->create([
        'product_id' => $product->id,
        'shipping_zone_id' => $zone->id,
        'extra_cost' => Money::fromMinor(2000),
    ]);

    $order = $this->action->handle(placeOrderData(
        [['product_id' => $product->id, 'qty' => 2]],
        ['shipping_zone_id' => $zone->id],
    ));

    // advance = effective shipping = 80 + 20×2 = 120.00
    expect($order->advance_amount->toMinor())->toBe(12000)
        ->and($order->shipping_cost->toMinor())->toBe(12000);
});

it('rejects an inactive shipping zone', function () {
    $product = Product::factory()->create(['price' => 1000, 'stock_amount' => 10, 'stock_status' => true]);
    $zone = ShippingZone::factory()->inactive()->create(['cost' => 80]);

    $this->action->handle(placeOrderData(
        [['product_id' => $product->id, 'qty' => 1]],
        ['shipping_zone_id' => $zone->id],
    ));
})->throws(DomainException::class);

it('rejects an unknown shipping zone', function () {
    $product = Product::factory()->create(['price' => 1000, 'stock_amount' => 10, 'stock_status' => true]);

    $this->action->handle(placeOrderData(
        [['product_id' => $product->id, 'qty' => 1]],
        ['shipping_zone_id' => 99999],
    ));
})->throws(DomainException::class);

it('computes a percentage advance_amount from the subtotal', function () {
    $product = Product::factory()->create([
        'price' => 1000, 'stock_amount' => 10, 'stock_status' => true,
        'is_advance_payment' => true, 'advance_payment_type' => 'partial',
        'partial_amount_type' => 'percentage', 'partial_amount' => 30,
    ]);

    $order = $this->action->handle(placeOrderData([['product_id' => $product->id, 'qty' => 2]]));

    expect($order->advance_amount->toMinor())->toBe(60000); // 30% of 2000.00
});

it('requires a delivery area when the product takes its advance as shipping', function () {
    $product = Product::factory()->create([
        'price' => 1000, 'stock_amount' => 10, 'stock_status' => true,
        'is_advance_payment' => true, 'advance_payment_type' => 'partial',
        'partial_amount_type' => 'shipping', 'partial_amount' => null,
    ]);

    $this->action->handle(placeOrderData([['product_id' => $product->id, 'qty' => 1]]));
})->throws(DomainException::class);

it('leaves advance_amount at zero for a non-advance product', function () {
    $product = Product::factory()->create([
        'price' => 1000, 'stock_amount' => 10, 'stock_status' => true,
        'is_advance_payment' => false,
    ]);

    $order = $this->action->handle(placeOrderData([['product_id' => $product->id, 'qty' => 1]]));

    expect($order->advance_amount->toMinor())->toBe(0);
});

it('keeps the price snapshot even if the product price later changes', function () {
    $product = Product::factory()->create(['price' => 1000, 'stock_amount' => 5, 'stock_status' => true]);

    $order = $this->action->handle(placeOrderData([['product_id' => $product->id, 'qty' => 1]]));
    $product->update(['price' => 9999]); // price changes after the order

    expect($order->items->first()->price->toMinor())->toBe(100000); // unchanged
});

it('creates and links the customer by mobile', function () {
    $product = Product::factory()->create(['price' => 500, 'stock_amount' => 5, 'stock_status' => true]);

    $order = $this->action->handle(placeOrderData([['product_id' => $product->id, 'qty' => 1]]));

    $customer = Customer::query()->where('mobile', '+8801712345678')->firstOrFail();
    expect($order->customer_id)->toBe($customer->id);
});

it('decrements stock on order placement', function () {
    $product = Product::factory()->create(['price' => 500, 'stock_amount' => 10, 'stock_status' => true]);

    $this->action->handle(placeOrderData([['product_id' => $product->id, 'qty' => 3]]));

    expect($product->refresh()->stock_amount)->toBe(7);
});

it('rejects an empty order', function () {
    $this->action->handle(placeOrderData([]));
})->throws(DomainException::class);

it('rejects an unknown product and rolls back (no order persisted)', function () {
    try {
        $this->action->handle(placeOrderData([['product_id' => 99999, 'qty' => 1]]));
    } catch (DomainException) {
        // expected
    }

    expect(Order::query()->count())->toBe(0);
});

it('rejects insufficient stock', function () {
    $product = Product::factory()->create(['price' => 500, 'stock_amount' => 1, 'stock_status' => true]);

    $this->action->handle(placeOrderData([['product_id' => $product->id, 'qty' => 5]]));
})->throws(DomainException::class);

it('rejects an out-of-stock product (stock_status false)', function () {
    $product = Product::factory()->create(['price' => 500, 'stock_amount' => 10, 'stock_status' => false]);

    $this->action->handle(placeOrderData([['product_id' => $product->id, 'qty' => 1]]));
})->throws(DomainException::class);

it('generates unique order numbers across many orders', function () {
    $product = Product::factory()->create(['price' => 100, 'stock_amount' => 100, 'stock_status' => true]);

    $numbers = collect(range(1, 15))->map(
        fn (): string => $this->action->handle(placeOrderData([['product_id' => $product->id, 'qty' => 1]]))->order_no,
    );

    expect($numbers->unique()->count())->toBe(15);
});
