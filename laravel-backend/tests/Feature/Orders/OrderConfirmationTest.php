<?php

declare(strict_types=1);

use App\Mail\OrderConfirmationMail;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Services\Settings\SettingsService;
use App\Support\Sms\FakeSmsGateway;
use App\Support\Sms\SmsGateway;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    cache()->flush();
    Mail::fake();
    $this->sms = new FakeSmsGateway;
    $this->app->instance(SmsGateway::class, $this->sms);

    // The single order-placed SMS goes through the notification system.
    app(SettingsService::class)->set('sms', 'enabled', true);
    cache()->flush();
});

function confirmableProduct(): Product
{
    return Product::factory()->create([
        'product_status' => 'published',
        'price' => 1000,
        'stock_amount' => 10,
        'stock_status' => true,
    ]);
}

it('sends an SMS and queues an email when the customer has an email', function () {
    $product = confirmableProduct();
    $zone = ShippingZone::factory()->create(['cost' => 80]);

    $response = $this->postJson('/api/v1/orders', [
        'items' => [['product_id' => $product->id, 'qty' => 1]],
        'customer' => ['name' => 'Karim', 'mobile' => '01712345678', 'email' => 'karim@furnib.test'],
        'shipping_zone_id' => $zone->id,
        'address' => 'House 1, Dhaka',
    ])->assertCreated();

    $orderNo = $response->json('data.order_no');

    // SMS sent to the customer mentioning the order number.
    $messages = $this->sms->messagesTo('+8801712345678');
    expect($messages)->toHaveCount(1)
        ->and($messages[0]['message'])->toContain($orderNo);

    // Email queued to the customer.
    Mail::assertQueued(OrderConfirmationMail::class, fn (OrderConfirmationMail $m) => $m->hasTo('karim@furnib.test'));
});

it('still sends an SMS but no email when the customer has no email', function () {
    $product = confirmableProduct();
    $zone = ShippingZone::factory()->create();

    $this->postJson('/api/v1/orders', [
        'items' => [['product_id' => $product->id, 'qty' => 1]],
        'customer' => ['name' => 'Karim', 'mobile' => '01712345678'],
        'shipping_zone_id' => $zone->id,
        'address' => 'House 1, Dhaka',
    ])->assertCreated();

    expect($this->sms->sent)->toHaveCount(1);
    Mail::assertNothingQueued();
});

it('does not fail the order when SMS delivery fails', function () {
    $this->sms->failNext();
    $product = confirmableProduct();
    $zone = ShippingZone::factory()->create();

    $this->postJson('/api/v1/orders', [
        'items' => [['product_id' => $product->id, 'qty' => 1]],
        'customer' => ['name' => 'Karim', 'mobile' => '01712345678'],
        'shipping_zone_id' => $zone->id,
        'address' => 'House 1, Dhaka',
    ])->assertCreated();
});
