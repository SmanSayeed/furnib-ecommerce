<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductShippingCharge;
use App\Models\ShippingZone;
use App\Support\Money;

it('returns active zones with base and this product per-unit extra', function () {
    $product = Product::factory()->create(['slug' => 'big-table', 'product_status' => 'published']);
    $inside = ShippingZone::factory()->create(['name' => 'Inside Dhaka', 'cost' => 80, 'status' => true, 'position_order' => 1]);
    $outside = ShippingZone::factory()->create(['name' => 'Outside Dhaka', 'cost' => 120, 'status' => true, 'position_order' => 2]);
    ShippingZone::factory()->inactive()->create(['name' => 'Hidden']);

    ProductShippingCharge::factory()->create([
        'product_id' => $product->id, 'shipping_zone_id' => $inside->id, 'extra_cost' => Money::fromMinor(2000),
    ]);
    ProductShippingCharge::factory()->create([
        'product_id' => $product->id, 'shipping_zone_id' => $outside->id, 'extra_cost' => Money::fromMinor(4000),
    ]);

    $response = $this->getJson('/api/v1/products/big-table/shipping-zones');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect($response->json('data.0.name'))->toBe('Inside Dhaka')
        ->and($response->json('data.0.base.minor'))->toBe(8000)
        ->and($response->json('data.0.extra_per_unit.minor'))->toBe(2000)
        ->and($response->json('data.1.base.minor'))->toBe(12000)
        ->and($response->json('data.1.extra_per_unit.minor'))->toBe(4000)
        ->and(collect($response->json('data'))->pluck('name'))->not->toContain('Hidden');
});

it('returns zero extra for zones the product has no charge for', function () {
    $product = Product::factory()->create(['slug' => 'plain-chair', 'product_status' => 'published']);
    ShippingZone::factory()->create(['name' => 'Inside Dhaka', 'cost' => 80, 'status' => true]);

    $response = $this->getJson('/api/v1/products/plain-chair/shipping-zones');

    $response->assertOk();
    expect($response->json('data.0.extra_per_unit.minor'))->toBe(0);
});

it('reports free shipping with zeroed base and extra for a free-shipping product', function () {
    $product = Product::factory()->create([
        'slug' => 'free-sofa', 'product_status' => 'published',
        'shipping_charge_allowed' => false,
    ]);
    $zone = ShippingZone::factory()->create(['name' => 'Inside Dhaka', 'cost' => 80, 'status' => true]);
    // Even a stale extra row must report zero once shipping is disabled.
    ProductShippingCharge::factory()->create([
        'product_id' => $product->id, 'shipping_zone_id' => $zone->id, 'extra_cost' => Money::fromMinor(2000),
    ]);

    $response = $this->getJson('/api/v1/products/free-sofa/shipping-zones');

    $response->assertOk()->assertJsonPath('free_shipping', true);
    expect($response->json('data.0.base.minor'))->toBe(0)
        ->and($response->json('data.0.extra_per_unit.minor'))->toBe(0);
});

it('sends the additional-unit rate so the storefront can price any quantity', function () {
    // The storefront runs ONE formula: base + extra + multi × (qty − 1).
    // The server derives `multi` (2-unit line − 1-unit line), which is what makes
    // that single formula correct whether or not the discount is on.
    $product = Product::factory()->create([
        'slug' => 'tulip-chair', 'product_status' => 'published',
        'multi_qty_shipping_enabled' => true,
    ]);
    $inside = ShippingZone::factory()->create(['name' => 'Inside Dhaka', 'cost' => 80, 'status' => true]);

    ProductShippingCharge::factory()->create([
        'product_id' => $product->id,
        'shipping_zone_id' => $inside->id,
        'extra_cost' => Money::fromMinor(2000),        // ৳20 first unit
        'multi_extra_cost' => Money::fromMinor(1000),  // ৳10 each additional
    ]);

    $response = $this->getJson('/api/v1/products/tulip-chair/shipping-zones');

    $response->assertOk()->assertJsonPath('multi_qty_enabled', true);

    $zone = $response->json('data.0');

    expect($zone['base']['minor'])->toBe(8000)
        ->and($zone['extra_per_unit']['minor'])->toBe(2000)
        ->and($zone['multi_extra_per_unit']['minor'])->toBe(1000);

    // The client formula, run here, must land on what PlaceOrder will charge.
    $shipping = fn (int $qty): int => $zone['base']['minor']
        + $zone['extra_per_unit']['minor']
        + $zone['multi_extra_per_unit']['minor'] * ($qty - 1);

    expect($shipping(1))->toBe(10000)   // ৳100
        ->and($shipping(2))->toBe(11000) // ৳110
        ->and($shipping(3))->toBe(12000); // ৳120
});

it('makes the single client formula collapse to per-unit when the option is off', function () {
    // No discount configured → the derived additional-unit rate EQUALS the extra,
    // so base + extra + extra × (qty − 1) = base + extra × qty. One formula, no
    // branching, nothing for the client to get wrong.
    $product = Product::factory()->create([
        'slug' => 'plain-table', 'product_status' => 'published',
        'multi_qty_shipping_enabled' => false,
    ]);
    $zone = ShippingZone::factory()->create(['cost' => 80, 'status' => true]);

    ProductShippingCharge::factory()->create([
        'product_id' => $product->id, 'shipping_zone_id' => $zone->id,
        'extra_cost' => Money::fromMinor(2000), 'multi_extra_cost' => null,
    ]);

    $row = $this->getJson('/api/v1/products/plain-table/shipping-zones')
        ->assertOk()
        ->assertJsonPath('multi_qty_enabled', false)
        ->json('data.0');

    expect($row['extra_per_unit']['minor'])->toBe(2000)
        ->and($row['multi_extra_per_unit']['minor'])->toBe(2000); // same → per-unit

    $shipping = fn (int $qty): int => $row['base']['minor']
        + $row['extra_per_unit']['minor']
        + $row['multi_extra_per_unit']['minor'] * ($qty - 1);

    expect($shipping(3))->toBe(14000); // 80 + 20×3 = ৳140, today's behaviour
});

it('404s for an unknown or unpublished product slug', function () {
    Product::factory()->draft()->create(['slug' => 'hidden-product']);

    $this->getJson('/api/v1/products/does-not-exist/shipping-zones')->assertNotFound();
    $this->getJson('/api/v1/products/hidden-product/shipping-zones')->assertNotFound();
});
