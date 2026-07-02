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

it('fires exactly one Purchase when a COD order is placed (placement is the conversion point)', function () {
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

    // The Purchase conversion now fires the moment an order is placed — the
    // afterResponse dispatch runs on kernel terminate. Even a COD (unpaid) order
    // counts as the sale here; admin status changes fire nothing.
    $purchases = $this->capi->ofType('Purchase');
    expect($purchases)->toHaveCount(1);
});
