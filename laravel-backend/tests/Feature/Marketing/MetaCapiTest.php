<?php

declare(strict_types=1);

use App\Actions\Marketing\ConfirmOrderPurchase;
use App\Models\Order;
use App\Services\Settings\SettingsService;
use App\Support\Capi\CapiEvents;
use App\Support\Capi\CapiUserData;
use App\Support\Capi\ConversionApi;
use App\Support\Capi\FakeConversionApi;
use App\Support\Payments\FakePaymentGateway;
use App\Support\Payments\PaymentGateway;

beforeEach(function () {
    cache()->flush();
    $this->gateway = new FakePaymentGateway;
    $this->capi = new FakeConversionApi;
    $this->app->instance(PaymentGateway::class, $this->gateway);
    $this->app->instance(ConversionApi::class, $this->capi);
});

it('sends a server-side Purchase event when the order is placed', function () {
    // Purchase fires once at order placement (Api\CheckoutController →
    // ConfirmOrderPurchase), NOT on payment — a paid order never auto-confirms.
    $order = Order::factory()->create(['total' => 5000, 'advance_paid' => 0, 'payment_status' => 'unpaid']);

    app(ConfirmOrderPurchase::class)->handle($order);

    $purchases = $this->capi->ofType('Purchase');
    expect($purchases)->toHaveCount(1)
        ->and($purchases[0]->eventId)->toBe('purchase.'.$order->order_no)
        ->and($purchases[0]->customData['order_id'])->toBe($order->order_no);
});

it('does not double-fire the Purchase event on repeat confirmation', function () {
    $order = Order::factory()->create(['total' => 5000, 'advance_paid' => 0, 'payment_status' => 'unpaid']);

    // The idempotency stamp (marketing_purchase_sent_at) guards a second call.
    app(ConfirmOrderPurchase::class)->handle($order);
    app(ConfirmOrderPurchase::class)->handle($order->refresh());

    expect($this->capi->ofType('Purchase'))->toHaveCount(1);
});

it('maps the order onto a Purchase payload with a shared dedup event id', function () {
    $order = Order::factory()->create(['total' => 5000]);

    $event = CapiEvents::purchase($order, new CapiUserData(email: 'Buyer@Example.com '));
    $payload = $event->toArray();

    expect($payload['event_name'])->toBe('Purchase')
        ->and($payload['event_id'])->toBe('purchase.'.$order->order_no)
        ->and($payload['custom_data']['currency'])->toBe('BDT')
        ->and($payload['custom_data']['value'])->toBe('5000.00')
        ->and($payload['custom_data']['order_id'])->toBe($order->order_no)
        // PII is SHA-256 hashed + normalized (lowercased/trimmed) before sending.
        ->and($payload['user_data']['em'])->toBe(hash('sha256', 'buyer@example.com'))
        ->and($payload)->not->toHaveKey('access_token');
});

it('never leaks the CAPI token through the payment response', function () {
    app(SettingsService::class)->set('marketing', 'fb_capi_token', 'EAAB-secret-capi', isSecret: true);
    $order = Order::factory()->create(['total' => 5000, 'advance_paid' => 0, 'payment_status' => 'unpaid']);

    $tranId = $this->postJson('/api/v1/payment/ssl/init', ['order_no' => $order->order_no, 'type' => 'full'])->json('tran_id');
    $this->gateway->fakeValidation(['status' => 'VALID', 'tran_id' => $tranId, 'amount' => $order->total->toDisplay(), 'val_id' => 'v']);

    $response = $this->postJson('/api/v1/payment/ssl/success', ['tran_id' => $tranId, 'val_id' => 'v'])->assertOk();

    expect($response->getContent())->not->toContain('EAAB-secret-capi');
});
