<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;

// Reset the rate limiter between tests; the 'orders' limiter is shared by IP and
// the array cache persists across the run, which would otherwise bleed the quota
// and turn 422s into 429s. Throttling itself is covered in CheckoutThrottleTest.
beforeEach(fn () => cache()->flush());

function publishedProduct(array $overrides = []): Product
{
    return Product::factory()->create(array_merge([
        'product_status' => 'published',
        'price' => 1000,
        'stock_amount' => 10,
        'stock_status' => true,
    ], $overrides));
}

function checkoutPayload(Product $product, ShippingZone $zone, array $overrides = []): array
{
    return array_merge([
        'items' => [['product_id' => $product->id, 'qty' => 2]],
        'customer' => ['name' => 'Karim', 'mobile' => '01712345678'],
        'shipping_zone_id' => $zone->id,
        'address' => 'House 1, Road 2, Dhaka',
    ], $overrides);
}

it('places an order and returns 201 with totals', function () {
    $product = publishedProduct();
    $zone = ShippingZone::factory()->create(['cost' => 80]);

    $response = $this->postJson('/api/v1/orders', checkoutPayload($product, $zone));

    $response->assertCreated()
        ->assertJsonPath('data.total.minor', 208000)   // 2000 + 80
        ->assertJsonPath('data.subtotal.minor', 200000);
    expect($response->json('data.order_no'))->toMatch('/^FNB-\d{8}-\d{4}$/');
});

it('captures the client IP on the order', function () {
    $product = publishedProduct();
    $zone = ShippingZone::factory()->create();

    $this->postJson('/api/v1/orders', checkoutPayload($product, $zone))->assertCreated();

    expect(Order::query()->first()->customer_ip)->not->toBeNull();
});

it('ignores any client-sent money and recomputes server-side', function () {
    $product = publishedProduct(['price' => 1000]);
    $zone = ShippingZone::factory()->create(['cost' => 80]);

    $this->postJson('/api/v1/orders', checkoutPayload($product, $zone, [
        'total' => 1,        // attacker-supplied — must be ignored
        'subtotal' => 1,
    ]))->assertCreated();

    expect(Order::query()->first()->total->toMinor())->toBe(208000);
});

it('rejects an invalid mobile', function () {
    $product = publishedProduct();
    $zone = ShippingZone::factory()->create();

    $response = $this->postJson('/api/v1/orders', checkoutPayload($product, $zone, [
        'customer' => ['name' => 'X', 'mobile' => '12345'],
    ]))->assertStatus(422)->assertJsonPath('error.code', 'validation_error');

    expect($response->json('error.details'))->toHaveKey('customer.mobile');
});

it('rejects empty items / missing address', function () {
    $response = $this->postJson('/api/v1/orders', [
        'items' => [],
        'customer' => ['name' => 'X', 'mobile' => '01712345678'],
    ])->assertStatus(422)->assertJsonPath('error.code', 'validation_error');

    expect($response->json('error.details'))->toHaveKeys(['items', 'address']);
});

it('rejects an unpublished product', function () {
    $draft = Product::factory()->draft()->create(['stock_amount' => 5, 'stock_status' => true]);
    $zone = ShippingZone::factory()->create();

    $response = $this->postJson('/api/v1/orders', checkoutPayload($draft, $zone))
        ->assertStatus(422)->assertJsonPath('error.code', 'validation_error');

    expect($response->json('error.details'))->toHaveKey('items.0.product_id');
});

it('rejects an inactive shipping zone', function () {
    $product = publishedProduct();
    $zone = ShippingZone::factory()->inactive()->create();

    $response = $this->postJson('/api/v1/orders', checkoutPayload($product, $zone))
        ->assertStatus(422)->assertJsonPath('error.code', 'validation_error');

    expect($response->json('error.details'))->toHaveKey('shipping_zone_id');
});
