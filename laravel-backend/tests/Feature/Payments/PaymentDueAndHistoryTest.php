<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;
use App\Support\Orders\PayLink;
use App\Support\Payments\FakePaymentGateway;
use App\Support\Payments\PaymentAmount;
use App\Support\Payments\PaymentGateway;

beforeEach(function () {
    cache()->flush();
    $this->gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $this->gateway);
});

function codOrder(array $overrides = []): Order
{
    $customer = Customer::factory()->create(['name' => 'Karim', 'mobile' => '+8801712345678']);

    return Order::factory()->create(array_merge([
        'customer_id' => $customer->id,
        'subtotal' => 4500,
        'shipping_cost' => 150,
        'total' => 4650,
        'advance_amount' => 0,
        'advance_paid' => 0,
        'payment_status' => 'unpaid',
        'status' => 'pending',
    ], $overrides));
}

function paySucceeds($test, Order $order, string $type): void
{
    $init = $test->postJson('/api/v1/payment/ssl/init', [
        'order_no' => $order->order_no,
        'type' => $type,
    ])->assertOk();

    $tranId = $init->json('tran_id');
    $amount = Payment::query()->where('tran_id', $tranId)->firstOrFail()->amount;

    $test->gateway->fakeValidation([
        'status' => 'VALID',
        'tran_id' => $tranId,
        'amount' => $amount->toDisplay(),
        'val_id' => 'v-'.$type,
    ]);

    $test->postJson('/api/v1/payment/ssl/success', ['tran_id' => $tranId, 'val_id' => 'v-'.$type])
        ->assertOk()
        ->assertJsonPath('status', 'success');
}

it('charges only the remaining due for a full payment after the delivery charge was paid', function () {
    $order = codOrder(); // total 4650, shipping 150

    // 1) Pay the delivery charge (150) first.
    paySucceeds($this, $order, 'shipping');
    $order->refresh();
    expect($order->advance_paid->toMinor())->toBe(15000)       // ৳150
        ->and($order->payment_status)->toBe('partial');

    // 2) "Pay Full Payment" must now bill the DUE (4650 − 150 = 4500), NOT 4650.
    expect(PaymentAmount::for($order, Payment::TYPE_FULL)->toMinor())->toBe(450000);

    // And a real full payment settles the order exactly — never over-collecting.
    paySucceeds($this, $order, 'full');
    $order->refresh();
    expect($order->advance_paid->toMinor())->toBe(465000)       // ৳4650 total, no overpay
        ->and($order->payment_status)->toBe('paid');
});

it('resolves remaining amounts against advance_paid for every type', function () {
    $order = codOrder([
        'advance_amount' => 3000,
        'advance_paid' => Money::fromMinor(10000),
        'payment_status' => 'partial',
    ]);
    // total 465000, paid 10000, advance target 300000, shipping 15000

    expect(PaymentAmount::for($order, Payment::TYPE_FULL)->toMinor())->toBe(455000)      // 465000 - 10000
        ->and(PaymentAmount::for($order, Payment::TYPE_PARTIAL)->toMinor())->toBe(290000) // 300000 - 10000
        ->and(PaymentAmount::for($order, Payment::TYPE_SHIPPING)->toMinor())->toBe(5000); // 15000 - 10000
});

it('stops offering the delivery button once the delivery charge is covered', function () {
    $order = codOrder();
    $token = PayLink::token($order->order_no);

    // Before paying: both buttons offered.
    $this->getJson("/api/v1/pay/{$order->order_no}/summary?t={$token}")
        ->assertJsonPath('data.can_pay_shipping', true)
        ->assertJsonPath('data.can_pay_full', true);

    paySucceeds($this, $order, 'shipping');

    // After paying delivery: only the full (remaining due) button remains.
    $this->getJson("/api/v1/pay/{$order->order_no}/summary?t={$token}")
        ->assertJsonPath('data.can_pay_shipping', false)
        ->assertJsonPath('data.can_pay_full', true)
        ->assertJsonPath('data.due_minor', 450000);
});

it('never offers a delivery button for a free-shipping order', function () {
    $order = codOrder(['subtotal' => 4650, 'shipping_cost' => 0, 'total' => 4650]);
    $token = PayLink::token($order->order_no);

    $this->getJson("/api/v1/pay/{$order->order_no}/summary?t={$token}")
        ->assertJsonPath('data.can_pay_shipping', false)
        ->assertJsonPath('data.can_pay_full', true)
        ->assertJsonPath('data.shipping_minor', 0);
});

it('exposes the payment history on the token-gated pay page', function () {
    $order = codOrder();
    $token = PayLink::token($order->order_no);

    paySucceeds($this, $order, 'shipping');

    $res = $this->getJson("/api/v1/pay/{$order->order_no}/summary?t={$token}")->assertOk();

    expect($res->json('data.payments'))->toHaveCount(1)
        ->and($res->json('data.payments.0.type'))->toBe('shipping')
        ->and($res->json('data.payments.0.status'))->toBe('success')
        ->and($res->json('data.payments.0.amount'))->toBe('Tk 150');
});

it('exposes pay options and history on the mobile-gated success status', function () {
    $order = codOrder();

    paySucceeds($this, $order, 'shipping');

    $res = $this->postJson("/api/v1/orders/{$order->order_no}/status", ['mobile' => '01712345678'])
        ->assertOk();

    expect($res->json('data.shipping_minor'))->toBe(15000)
        ->and($res->json('data.due_minor'))->toBe(450000)
        ->and($res->json('data.can_pay_shipping'))->toBe(false) // already covered
        ->and($res->json('data.can_pay_full'))->toBe(true)
        ->and($res->json('data.free_shipping'))->toBe(false)
        ->and($res->json('data.payments'))->toHaveCount(1)
        ->and($res->json('data.payments.0.type'))->toBe('shipping');
});

it('hides failed and cancelled attempts from the customer history', function () {
    $order = codOrder();

    // A cancelled attempt then a successful shipping payment.
    $init = $this->postJson('/api/v1/payment/ssl/init', ['order_no' => $order->order_no, 'type' => 'full'])->assertOk();
    $this->post('/api/v1/payment/ssl/cancel', ['tran_id' => $init->json('tran_id')]);

    paySucceeds($this, $order, 'shipping');

    $token = PayLink::token($order->order_no);
    $res = $this->getJson("/api/v1/pay/{$order->order_no}/summary?t={$token}")->assertOk();

    // Only the successful shipping row is shown (the cancelled full is hidden).
    expect($res->json('data.payments'))->toHaveCount(1)
        ->and($res->json('data.payments.0.type'))->toBe('shipping');
});
