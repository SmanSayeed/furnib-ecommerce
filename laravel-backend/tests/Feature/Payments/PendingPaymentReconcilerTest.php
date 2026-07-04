<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PendingPaymentReconciler;
use App\Services\Settings\SettingsService;
use App\Support\Money;
use App\Support\Payments\FakePaymentGateway;
use App\Support\Payments\PaymentGateway;
use Illuminate\Support\Carbon;

beforeEach(function () {
    cache()->flush();
    $this->gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $this->gateway);

    // The sweep short-circuits unless SSLCommerz is configured.
    $settings = app(SettingsService::class);
    $settings->set('sslcommerz', 'store_id', 'test_store', false);
    $settings->set('sslcommerz', 'store_passwd', 'test_pass', true);
    cache()->flush();

    $this->reconciler = app(PendingPaymentReconciler::class);
});

function pendingPayment(Order $order, string $tranId, int $amountMinor, int $ageMinutes = 30): Payment
{
    $payment = Payment::query()->create([
        'order_id' => $order->id,
        'gateway' => 'sslcommerz',
        'amount' => Money::fromMinor($amountMinor),
        'type' => 'full',
        'tran_id' => $tranId,
        'status' => Payment::STATUS_PENDING,
    ]);

    // Backdate past the grace window without tripping updated_at guards.
    $payment->forceFill(['created_at' => Carbon::now()->subMinutes($ageMinutes)])->saveQuietly();

    return $payment->refresh();
}

it('recovers a genuine payment whose callback and IPN were both lost', function () {
    $order = Order::factory()->create(['total' => 10100, 'advance_paid' => 0, 'payment_status' => 'unpaid']);
    pendingPayment($order, 'FNBPAY-LOST1', $order->total->toMinor());

    $this->gateway->fakeQuery('FNBPAY-LOST1', [
        'status' => 'VALID',
        'tran_id' => 'FNBPAY-LOST1',
        'amount' => $order->total->toDisplay(),
        'currency' => 'BDT',
        'val_id' => 'v-recovered',
    ]);

    $summary = $this->reconciler->sweep();

    expect($summary['recovered'])->toBe(1);
    expect(Payment::query()->where('tran_id', 'FNBPAY-LOST1')->first()->status)->toBe('success');
    expect($order->fresh()->payment_status)->toBe('paid')
        ->and($order->fresh()->advance_paid->toMinor())->toBe($order->total->toMinor());
});

it('is idempotent — a late IPN after recovery does not double-apply', function () {
    $order = Order::factory()->create(['total' => 10100, 'advance_paid' => 0, 'payment_status' => 'unpaid']);
    pendingPayment($order, 'FNBPAY-DUP1', $order->total->toMinor());

    $this->gateway->fakeQuery('FNBPAY-DUP1', [
        'status' => 'VALID', 'tran_id' => 'FNBPAY-DUP1',
        'amount' => $order->total->toDisplay(), 'currency' => 'BDT', 'val_id' => 'v1',
    ]);

    $this->reconciler->sweep();
    $this->reconciler->sweep(); // second run — already success, nothing to sweep

    expect(Payment::query()->where('order_id', $order->id)->where('status', 'success')->count())->toBe(1);
    expect($order->fresh()->advance_paid->toMinor())->toBe($order->total->toMinor());
});

it('rejects a queried transaction whose amount does not reconcile', function () {
    $order = Order::factory()->create(['total' => 10100, 'advance_paid' => 0, 'payment_status' => 'unpaid']);
    pendingPayment($order, 'FNBPAY-TAMPER', $order->total->toMinor());

    $this->gateway->fakeQuery('FNBPAY-TAMPER', [
        'status' => 'VALID', 'tran_id' => 'FNBPAY-TAMPER',
        'amount' => 1, 'currency' => 'BDT', 'val_id' => 'v-bad',
    ]);

    $summary = $this->reconciler->sweep();

    expect($summary['recovered'])->toBe(0)
        ->and($summary['failed'])->toBe(1);
    expect($order->fresh()->payment_status)->toBe('unpaid');
});

it('marks a pending row failed when the gateway reports a dead status', function () {
    $order = Order::factory()->create(['total' => 10100, 'payment_status' => 'unpaid']);
    pendingPayment($order, 'FNBPAY-DEAD', $order->total->toMinor());

    $this->gateway->fakeQuery('FNBPAY-DEAD', ['status' => 'CANCELLED', 'tran_id' => 'FNBPAY-DEAD']);

    $summary = $this->reconciler->sweep();

    expect($summary['failed'])->toBe(1);
    $payment = Payment::query()->where('tran_id', 'FNBPAY-DEAD')->first();
    expect($payment->status)->toBe('failed')
        ->and($payment->note)->toContain('CANCELLED');
});

it('leaves a payment pending when the gateway has no record yet', function () {
    $order = Order::factory()->create(['total' => 10100, 'payment_status' => 'unpaid']);
    pendingPayment($order, 'FNBPAY-NONE', $order->total->toMinor());

    // No fakeQuery scripted → gateway returns null.
    $summary = $this->reconciler->sweep();

    expect($summary['still_pending'])->toBe(1)
        ->and($summary['recovered'])->toBe(0);
    expect(Payment::query()->where('tran_id', 'FNBPAY-NONE')->first()->status)->toBe('pending');
});

it('does not sweep payments still inside the grace window', function () {
    $order = Order::factory()->create(['total' => 10100, 'payment_status' => 'unpaid']);
    pendingPayment($order, 'FNBPAY-FRESH', $order->total->toMinor(), ageMinutes: 1);

    $this->gateway->fakeQuery('FNBPAY-FRESH', [
        'status' => 'VALID', 'tran_id' => 'FNBPAY-FRESH',
        'amount' => $order->total->toDisplay(), 'currency' => 'BDT', 'val_id' => 'v-fresh',
    ]);

    $summary = $this->reconciler->sweep();

    // Too new — the normal callback/IPN still has time to arrive.
    expect($summary['swept'])->toBe(0);
    expect(Payment::query()->where('tran_id', 'FNBPAY-FRESH')->first()->status)->toBe('pending');
});

it('leaves the row pending when the gateway query API errors', function () {
    $order = Order::factory()->create(['total' => 10100, 'payment_status' => 'unpaid']);
    pendingPayment($order, 'FNBPAY-ERR', $order->total->toMinor());

    // A gateway that throws on query (transport failure) must never mark failed.
    $throwing = new class implements PaymentGateway
    {
        public function initSession(Order $order, Money $amount, string $tranId): string
        {
            return '';
        }

        public function validatePayment(string $valId): array
        {
            return [];
        }

        public function queryTransaction(string $tranId): ?array
        {
            throw new RuntimeException('gateway down');
        }

        public function verifyCallback(array $payload): bool
        {
            return true;
        }
    };
    $this->app->instance(PaymentGateway::class, $throwing);

    $summary = app(PendingPaymentReconciler::class)->sweep();

    expect($summary['still_pending'])->toBe(1)
        ->and($summary['failed'])->toBe(0);
    expect(Payment::query()->where('tran_id', 'FNBPAY-ERR')->first()->status)->toBe('pending');
});
