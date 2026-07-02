<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Support\Capi\CapiUserData;
use App\Support\Marketing\OrderTrackingPayload;

beforeEach(function () {
    cache()->flush();
});

it('persists fbp/fbc on the order and returns a purchase tracking payload', function () {
    $category = Category::factory()->create(['title' => 'Sofas']);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'product_status' => 'published',
        'price' => 1000, 'stock_amount' => 10, 'stock_status' => true,
        'sku' => 'SKU-1',
    ]);
    $zone = ShippingZone::factory()->create(['name' => 'Dhaka', 'cost' => 80, 'status' => true]);

    $res = $this->withHeaders(['X-Fbp' => 'fb.1.123.abc', 'X-Fbc' => 'fb.1.123.click'])
        ->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'qty' => 2]],
            'customer' => ['name' => 'Karim Mia', 'mobile' => '01712345678'],
            'shipping_zone_id' => $zone->id,
            'address' => 'House 1, Road 2, Dhaka',
        ])->assertCreated();

    $order = Order::query()->latest('id')->firstOrFail();

    // The first-party cookies are captured onto the order so the purchase
    // conversion (fired at placement) attributes to this customer.
    expect($order->fbp)->toBe('fb.1.123.abc')
        ->and($order->fbc)->toBe('fb.1.123.click');

    $t = $res->json('data.tracking');

    expect($t['event'])->toBe('purchase')
        ->and($t['event_id'])->toBe('purchase.'.$order->order_no)
        ->and($t['ecommerce']['currency'])->toBe('BDT')
        ->and((float) $t['ecommerce']['value'])->toBe($order->total->toDisplay())
        ->and($t['ecommerce']['payment_method'])->toBe('cod')
        ->and($t['ecommerce']['items'])->toHaveCount(1)
        ->and($t['ecommerce']['items'][0]['item_id'])->toBe('SKU-1')
        ->and($t['ecommerce']['items'][0]['item_name'])->toBe($product->title)
        ->and($t['ecommerce']['items'][0]['quantity'])->toBe(2)
        ->and($t['ecommerce']['items'][0]['item_category'])->toBe('Sofas')
        ->and($t['order_info']['order_id'])->toBe($order->order_no)
        ->and($t['order_info']['item_count'])->toBe(2)
        ->and($t['order_info']['payment_status'])->toBe('unpaid')
        ->and($t['user_data']['area'])->toBe('Dhaka')
        ->and($t['user_data']['fbp'])->toBe('fb.1.123.abc')
        ->and($t['user_data']['fbc'])->toBe('fb.1.123.click');
});

it('emits both raw and Meta-normalized hashed PII in the tracking user_data', function () {
    $category = Category::factory()->create(['title' => 'Chairs']);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'product_status' => 'published',
        'price' => 500, 'stock_amount' => 5, 'stock_status' => true,
    ]);

    $this->postJson('/api/v1/orders', [
        'items' => [['product_id' => $product->id, 'qty' => 1]],
        'customer' => ['name' => 'Karim Mia', 'mobile' => '01712345678', 'email' => 'Buyer@Example.com'],
        'address' => 'House 1, Road 2, Dhaka',
    ])->assertCreated();

    $order = Order::query()->latest('id')->firstOrFail();
    $payload = OrderTrackingPayload::for($order);
    $ud = $payload['user_data'];

    expect($ud['name'])->toBe('Karim Mia')
        ->and($ud['phone'])->toBe($order->customer?->mobile)
        ->and($ud['address'])->toBe('House 1, Road 2, Dhaka')
        ->and($ud['hashed_name'])->toBe(CapiUserData::hashName('Karim Mia'))
        ->and($ud['hashed_phone'])->toBe(CapiUserData::hashPhone($order->customer?->mobile))
        ->and($ud['hashed_email'])->toBe(hash('sha256', 'buyer@example.com'))
        ->and($ud['client_ip'])->toBe($order->customer_ip)
        ->and($ud['customer_id'])->toBe($order->customer_id);
});
