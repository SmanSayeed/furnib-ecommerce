<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Payment;

use App\Actions\Payments\RecordPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InitPaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Payments\PaymentAmount;
use App\Support\Payments\PaymentGateway;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * SSLCommerz checkout endpoints. `init` opens a hosted session; the gateway
 * later calls `success`/`fail`/`cancel`/`ipn`. Acceptance NEVER trusts the
 * redirect — every callback re-validates server-side via the validation API
 * before money is recorded. Store credentials stay server-side at all times.
 */
class SslController extends Controller
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPayment $recordPayment,
    ) {}

    public function init(InitPaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $order = Order::query()->where('order_no', $validated['order_no'])->firstOrFail();

        if ($order->payment_status === 'paid') {
            return $this->error(422, 'already_paid', 'This order is already paid.');
        }

        $amount = PaymentAmount::for($order, $validated['type']);

        if ($amount->toMinor() <= 0) {
            return $this->error(422, 'nothing_to_pay', 'There is nothing to pay for this option.');
        }

        $tranId = $this->newTransactionId();

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'sslcommerz',
            'amount' => $amount,
            'type' => $validated['type'],
            'tran_id' => $tranId,
            'status' => Payment::STATUS_PENDING,
        ]);

        $gatewayUrl = $this->gateway->initSession($order, $amount, $payment->tran_id);

        return response()->json([
            'gateway_url' => $gatewayUrl,
            'tran_id' => $payment->tran_id,
        ]);
    }

    public function success(Request $request): JsonResponse
    {
        return $this->finalize($request);
    }

    public function ipn(Request $request): JsonResponse
    {
        return $this->finalize($request);
    }

    public function fail(Request $request): JsonResponse
    {
        return $this->markFailed($request);
    }

    public function cancel(Request $request): JsonResponse
    {
        return $this->markFailed($request);
    }

    /**
     * Validate server-side and record the payment (idempotent).
     */
    private function finalize(Request $request): JsonResponse
    {
        $tranId = (string) $request->input('tran_id', '');
        $valId = (string) $request->input('val_id', '');

        if ($tranId === '' || $valId === '') {
            return $this->error(422, 'invalid_callback', 'Missing transaction or validation id.');
        }

        $validated = $this->gateway->validatePayment($valId);

        try {
            $payment = $this->recordPayment->handle($tranId, $validated);
        } catch (DomainException) {
            return $this->error(404, 'unknown_transaction', 'Unknown transaction.');
        }

        $payment->loadMissing('order');

        return response()->json([
            'status' => $payment->status,
            'order_no' => $payment->order->order_no,
            'payment_status' => $payment->order->payment_status,
        ]);
    }

    private function markFailed(Request $request): JsonResponse
    {
        $tranId = (string) $request->input('tran_id', '');

        $payment = Payment::query()->where('tran_id', $tranId)->first();

        if ($payment !== null && $payment->status === Payment::STATUS_PENDING) {
            $payment->update(['status' => Payment::STATUS_FAILED]);
        }

        return response()->json(['status' => Payment::STATUS_FAILED]);
    }

    private function newTransactionId(): string
    {
        do {
            $tranId = 'FNBPAY-'.Str::upper(Str::random(18));
        } while (Payment::query()->where('tran_id', $tranId)->exists());

        return $tranId;
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
