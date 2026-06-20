<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShippingZone;
use Illuminate\Support\Facades\URL;

it('serves the invoice PDF through a valid signed URL', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->create();

    $url = URL::temporarySignedRoute('invoice.public', now()->addDay(), ['order' => $order->id]);

    $response = $this->get($url)->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

it('rejects an unsigned invoice request (no enumeration)', function () {
    $order = Order::factory()->create();

    $this->get("/invoice/{$order->id}")->assertForbidden();
});

it('rejects a tampered signature', function () {
    $order = Order::factory()->create();

    $url = URL::temporarySignedRoute('invoice.public', now()->addDay(), ['order' => $order->id]);

    $this->get($url.'tampered')->assertForbidden();
});

it('includes a working signed invoice_url in the checkout response', function () {
    cache()->flush();
    $product = Product::factory()->create([
        'product_status' => 'published', 'price' => 1000, 'stock_amount' => 5, 'stock_status' => true,
    ]);
    $zone = ShippingZone::factory()->create(['cost' => 80]);

    $invoiceUrl = $this->postJson('/api/v1/orders', [
        'items' => [['product_id' => $product->id, 'qty' => 1]],
        'customer' => ['name' => 'Karim', 'mobile' => '01712345678'],
        'shipping_zone_id' => $zone->id,
        'address' => 'House 1, Dhaka',
    ])->assertCreated()->json('data.invoice_url');

    expect($invoiceUrl)->toBeString()->toContain('/invoice/');

    $this->get($invoiceUrl)->assertOk();
});
