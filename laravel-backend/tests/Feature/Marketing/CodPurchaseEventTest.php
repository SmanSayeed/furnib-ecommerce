<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ShippingZone;
use App\Support\Capi\ConversionApi;
use App\Support\Capi\FakeConversionApi;

beforeEach(function () {
    cache()->flush();
    $this->capi = new FakeConversionApi;
    $this->app->instance(ConversionApi::class, $this->capi);
});

it('does NOT fire a Purchase when an order is merely placed (an order is not a confirmed sale)', function () {
    $product = Product::factory()->create([
        'sku' => 'SOFA-9', 'price' => 1000, 'stock_amount' => 10,
        'stock_status' => true, 'product_status' => 'published',
    ]);
    $zone = ShippingZone::factory()->create(['cost' => 80, 'status' => true]);

    $this->postJson('/api/v1/orders', [
        'items' => [['product_id' => $product->id, 'qty' => 2]],
        'customer' => ['name' => 'Karim', 'mobile' => '01712345678', 'email' => 'karim@example.com'],
        'shipping_zone_id' => $zone->id,
        'address' => 'House 1, Road 2, Dhaka',
    ])->assertStatus(201);

    // The Purchase conversion fires only when the admin confirms the order
    // (see Admin\OrderController::updateStatus) — never at placement.
    expect($this->capi->ofType('Purchase'))->toHaveCount(0);
});
