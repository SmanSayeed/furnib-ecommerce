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

it('fires a Purchase to Meta when a COD order is placed (no gateway payment)', function () {
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

    $purchases = $this->capi->ofType('Purchase');
    expect($purchases)->toHaveCount(1);

    $payload = $purchases[0]->toArray();
    expect($payload['custom_data']['value'])->toBe('2080.00')        // 2×1000 + 80 shipping
        ->and($payload['custom_data']['content_ids'])->toBe(['SOFA-9'])
        // Customer PII is hashed (never sent in the clear).
        ->and($payload['user_data']['ph'])->toBe(hash('sha256', '8801712345678'))
        ->and($payload['user_data']['em'])->toBe(hash('sha256', 'karim@example.com'));
});
