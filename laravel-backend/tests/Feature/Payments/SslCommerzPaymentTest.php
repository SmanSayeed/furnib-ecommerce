<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Payment;
use App\Services\Settings\SettingsService;
use App\Support\Payments\FakePaymentGateway;
use App\Support\Payments\PaymentAmount;
use App\Support\Payments\PaymentGateway;

beforeEach(function () {
    cache()->flush();
    $this->gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $this->gateway);
});

function payableOrder(array $overrides = []): Order
{
    return Order::factory()->create(array_merge([
        'subtotal' => 10000,
        'shipping_cost' => 100,
        'total' => 10100,
        'advance_paid' => 0,
        'payment_status' => 'unpaid',
        'status' => 'pending',
    ], $overrides));
}

function initPayment($test, Order $order, string $type = 'full'): string
{
    $response = $test->postJson('/api/v1/payment/ssl/init', [
        'order_no' => $order->order_no,
        'type' => $type,
    ])->assertOk();

    return $response->json('tran_id');
}

it('opens a session and stores a pending payment for the resolved amount', function () {
    $order = payableOrder();

    $response = $this->postJson('/api/v1/payment/ssl/init', [
        'order_no' => $order->order_no,
        'type' => 'full',
    ])->assertOk();

    expect($response->json('gateway_url'))->toContain('sslcommerz')
        ->and($response->json('tran_id'))->toStartWith('FNBPAY-');

    $payment = Payment::query()->where('tran_id', $response->json('tran_id'))->firstOrFail();
    expect($payment->status)->toBe('pending')
        ->and($payment->amount->toMinor())->toBe($order->total->toMinor())
        ->and($this->gateway->sessions)->toHaveCount(1);
});

it('rejects a forged/redirect-only success the gateway does not validate', function () {
    $order = payableOrder();
    $tranId = initPayment($this, $order);

    // Gateway validation returns INVALID (default) — attacker just hit success_url.
    $this->postJson('/api/v1/payment/ssl/success', ['tran_id' => $tranId, 'val_id' => 'forged'])
        ->assertOk()
        ->assertJsonPath('status', 'failed');

    $order->refresh();
    expect($order->payment_status)->toBe('unpaid')
        ->and($order->advance_paid->toMinor())->toBe(0)
        ->and(Payment::query()->where('tran_id', $tranId)->first()->status)->toBe('failed');
});

it('rejects a VALID transaction whose amount does not reconcile', function () {
    $order = payableOrder();
    $tranId = initPayment($this, $order);

    // Gateway says VALID but for a tampered (lower) amount.
    $this->gateway->fakeValidation(['status' => 'VALID', 'tran_id' => $tranId, 'amount' => 1, 'val_id' => 'v1']);

    $this->postJson('/api/v1/payment/ssl/success', ['tran_id' => $tranId, 'val_id' => 'v1'])
        ->assertOk()
        ->assertJsonPath('status', 'failed');

    expect($order->fresh()->payment_status)->toBe('unpaid');
});

it('accepts a genuine full payment and marks the order paid + confirmed', function () {
    $order = payableOrder();
    $tranId = initPayment($this, $order);

    $this->gateway->fakeValidation([
        'status' => 'VALID',
        'tran_id' => $tranId,
        'amount' => $order->total->toDisplay(),
        'val_id' => 'v-ok',
    ]);

    $this->postJson('/api/v1/payment/ssl/success', ['tran_id' => $tranId, 'val_id' => 'v-ok'])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('payment_status', 'paid');

    $order->refresh();
    expect($order->payment_status)->toBe('paid')
        ->and($order->advance_paid->toMinor())->toBe($order->total->toMinor())
        ->and($order->status)->toBe('confirmed');
});

it('is idempotent — a duplicate IPN/return records the money only once', function () {
    $order = payableOrder();
    $tranId = initPayment($this, $order);

    $this->gateway->fakeValidation([
        'status' => 'VALID',
        'tran_id' => $tranId,
        'amount' => $order->total->toDisplay(),
        'val_id' => 'v-ok',
    ]);

    $payload = ['tran_id' => $tranId, 'val_id' => 'v-ok'];
    $this->postJson('/api/v1/payment/ssl/success', $payload)->assertOk();
    $this->postJson('/api/v1/payment/ssl/ipn', $payload)->assertOk();

    expect(Payment::query()->where('tran_id', $tranId)->count())->toBe(1)
        ->and(Payment::query()->where('order_id', $order->id)->where('status', 'success')->count())->toBe(1);

    $order->refresh();
    expect($order->advance_paid->toMinor())->toBe($order->total->toMinor())
        ->and($order->payment_status)->toBe('paid');
});

it('records a partial (advance) payment and leaves the order partially paid', function () {
    $order = payableOrder(['advance_paid' => 3000]); // 3000.00 taka required advance
    $tranId = initPayment($this, $order, 'partial');

    $this->gateway->fakeValidation([
        'status' => 'VALID',
        'tran_id' => $tranId,
        'amount' => 3000,
        'val_id' => 'v-part',
    ]);

    $this->postJson('/api/v1/payment/ssl/success', ['tran_id' => $tranId, 'val_id' => 'v-part'])
        ->assertOk()
        ->assertJsonPath('payment_status', 'partial');

    expect($order->fresh()->advance_paid->toMinor())->toBe(300000);
});

it('records a shipping-only payment', function () {
    $order = payableOrder();
    $tranId = initPayment($this, $order, 'shipping');

    $this->gateway->fakeValidation([
        'status' => 'VALID',
        'tran_id' => $tranId,
        'amount' => $order->shipping_cost->toDisplay(),
        'val_id' => 'v-ship',
    ]);

    $this->postJson('/api/v1/payment/ssl/success', ['tran_id' => $tranId, 'val_id' => 'v-ship'])
        ->assertOk()
        ->assertJsonPath('payment_status', 'partial');

    expect($order->fresh()->advance_paid->toMinor())->toBe($order->shipping_cost->toMinor());
});

it('resolves the correct charge amount per payment type', function () {
    $order = payableOrder(['advance_paid' => 3000]);

    expect(PaymentAmount::for($order, Payment::TYPE_FULL)->toMinor())->toBe(1010000)
        ->and(PaymentAmount::for($order, Payment::TYPE_PARTIAL)->toMinor())->toBe(300000)
        ->and(PaymentAmount::for($order, Payment::TYPE_SHIPPING)->toMinor())->toBe(10000);
});

it('rejects initiating a payment on an already-paid order', function () {
    $order = payableOrder(['payment_status' => 'paid']);

    $this->postJson('/api/v1/payment/ssl/init', ['order_no' => $order->order_no, 'type' => 'full'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'already_paid');
});

it('returns 404 for a callback on an unknown transaction', function () {
    $this->postJson('/api/v1/payment/ssl/success', ['tran_id' => 'FNBPAY-NOPE', 'val_id' => 'x'])
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'unknown_transaction');
});

it('keeps gateway secret credentials out of any client response', function () {
    $settings = app(SettingsService::class);
    $settings->set('sslcommerz', 'store_id', 'furnib_store', false);
    $settings->set('sslcommerz', 'store_passwd', 'super-secret-pass', true);

    // Public view masks the secret; secrets are only readable server-side.
    expect($settings->toArray('sslcommerz'))->toMatchArray(['store_passwd' => null])
        ->and($settings->toArray('sslcommerz', includeSecrets: true)['store_passwd'])->toBe('super-secret-pass');

    $order = payableOrder();
    $response = $this->postJson('/api/v1/payment/ssl/init', ['order_no' => $order->order_no, 'type' => 'full']);

    expect($response->getContent())->not->toContain('super-secret-pass')
        ->and($response->getContent())->not->toContain('store_passwd');
});
