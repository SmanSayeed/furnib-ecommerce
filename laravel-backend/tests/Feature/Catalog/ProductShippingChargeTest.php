<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductShippingCharge;
use App\Models\ShippingZone;
use App\Support\Money;
use Illuminate\Database\QueryException;

it('exposes a per-unit extra cost for a zone the product has a charge for', function () {
    $product = Product::factory()->create();
    $zone = ShippingZone::factory()->create();

    ProductShippingCharge::factory()->create([
        'product_id' => $product->id,
        'shipping_zone_id' => $zone->id,
        'extra_cost' => Money::fromMinor(2000), // ৳20
    ]);

    expect($product->extraMinorFor($zone->id))->toBe(2000);
});

it('returns zero extra for a zone the product has no charge for', function () {
    $product = Product::factory()->create();
    $zone = ShippingZone::factory()->create();

    expect($product->extraMinorFor($zone->id))->toBe(0);
});

it('relates shipping charges to the product', function () {
    $product = Product::factory()->create();
    $zoneA = ShippingZone::factory()->create();
    $zoneB = ShippingZone::factory()->create();

    ProductShippingCharge::factory()->create([
        'product_id' => $product->id,
        'shipping_zone_id' => $zoneA->id,
        'extra_cost' => Money::fromMinor(2000),
    ]);
    ProductShippingCharge::factory()->create([
        'product_id' => $product->id,
        'shipping_zone_id' => $zoneB->id,
        'extra_cost' => Money::fromMinor(4000),
    ]);

    expect($product->shippingCharges()->count())->toBe(2)
        ->and($product->extraMinorFor($zoneB->id))->toBe(4000);
});

it('enforces one charge per product+zone pair', function () {
    $product = Product::factory()->create();
    $zone = ShippingZone::factory()->create();

    ProductShippingCharge::factory()->create([
        'product_id' => $product->id,
        'shipping_zone_id' => $zone->id,
        'extra_cost' => Money::fromMinor(2000),
    ]);

    expect(fn () => ProductShippingCharge::factory()->create([
        'product_id' => $product->id,
        'shipping_zone_id' => $zone->id,
        'extra_cost' => Money::fromMinor(3000),
    ]))->toThrow(QueryException::class);
});
