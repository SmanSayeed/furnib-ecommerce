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
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

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

        // The gateway throws when credentials are not configured or the remote
        // session can't be opened. Fail the pending payment and return a clean,
        // friendly error instead of a 500 — COD is always available.
        try {
            $gatewayUrl = $this->gateway->initSession($order, $amount, $payment->tran_id);
        } catch (RuntimeException $e) {
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'note' => 'Online payment could not be started (gateway unavailable).',
            ]);
            report($e);

            return $this->error(
                503,
                'payment_unavailable',
                'Online payment is currently unavailable. You can pay cash on delivery.',
            );
        }

        return response()->json([
            'gateway_url' => $gatewayUrl,
            'tran_id' => $payment->tran_id,
        ]);
    }

    // Browser-facing callback: SSLCommerz POSTs here and the shopper's browser
    // follows, so we redirect to the storefront result page (JSON only for
    // API/test clients that ask for it).
    public function success(Request $request): Response
    {
        return $this->finalize($request, forceJson: false);
    }

    // Server-to-server webhook (no browser): always answer JSON. This is the
    // authoritative, most reliable path — it arrives even if the shopper closes
    // the tab after paying.
    public function ipn(Request $request): Response
    {
        return $this->finalize($request, forceJson: true);
    }

    public function fail(Request $request): Response
    {
        return $this->markUnpaid($request, Payment::STATUS_FAILED, 'failed');
    }

    public function cancel(Request $request): Response
    {
        return $this->markUnpaid($request, Payment::STATUS_CANCELLED, 'cancelled');
    }

    /**
     * Verify the callback is genuine, validate the transaction SERVER-SIDE, and
     * record the payment (idempotent). The redirect/IPN POST is never trusted on
     * its own — money moves only after validatePayment() confirms it.
     */
    private function finalize(Request $request, bool $forceJson): Response
    {
        // Cheap authenticity pre-check (verify_sign) before any outbound call.
        if (! $this->gateway->verifyCallback($request->all())) {
            return $this->respondError($request, $forceJson, 422, 'invalid_signature', 'Signature verification failed.', $request);
        }

        $tranId = (string) $request->input('tran_id', '');
        $valId = (string) $request->input('val_id', '');

        if ($tranId === '' || $valId === '') {
            return $this->respondError($request, $forceJson, 422, 'invalid_callback', 'Missing transaction or validation id.', $request);
        }

        $validated = $this->gateway->validatePayment($valId);

        try {
            $payment = $this->recordPayment->handle($tranId, $validated);
        } catch (DomainException) {
            return $this->respondError($request, $forceJson, 404, 'unknown_transaction', 'Unknown transaction.', $request);
        }

        $payment->loadMissing('order');
        $success = $payment->status === Payment::STATUS_SUCCESS;

        return $this->respondOk(
            $request,
            $forceJson,
            [
                'status' => $payment->status,
                'order_no' => $payment->order->order_no,
                'payment_status' => $payment->order->payment_status,
            ],
            $success ? 'success' : 'failed',
            $payment->order->order_no,
        );
    }

    /**
     * Record a non-successful outcome — the customer cancelled at the gateway,
     * or the gateway/bank declined — against the pending payment, with an
     * auto-generated reason note. The customer is NEVER asked for the reason;
     * the server derives it from the callback.
     */
    private function markUnpaid(Request $request, string $status, string $resultStatus): Response
    {
        $tranId = (string) $request->input('tran_id', '');

        $payment = Payment::query()->where('tran_id', $tranId)->first();
        $payment?->loadMissing('order');

        if ($payment !== null && $payment->status === Payment::STATUS_PENDING) {
            $payment->update([
                'status' => $status,
                'note' => $this->outcomeNote($status, $request),
            ]);
        }

        return $this->respondOk(
            $request,
            forceJson: false,
            json: ['status' => $status],
            resultStatus: $resultStatus,
            orderNo: $payment?->order?->order_no ?? $this->orderNoFrom($request),
        );
    }

    /**
     * A short, non-sensitive reason for a cancelled/failed payment, auto-derived
     * from the gateway callback. Never PII, never asked of the customer.
     */
    private function outcomeNote(string $status, Request $request): string
    {
        if ($status === Payment::STATUS_CANCELLED) {
            return 'Cancelled by customer at the payment gateway.';
        }

        // SSLCommerz echoes a human reason on failures via `error` (occasionally
        // `failedreason`). Keep it short and safe.
        $reason = trim((string) ($request->input('error') ?: $request->input('failedreason', '')));

        return $reason !== ''
            ? 'Payment failed at the gateway: '.Str::limit($reason, 180)
            : 'Payment failed at the payment gateway.';
    }

    /**
     * JSON for API/test clients; a browser redirect to the storefront result
     * page for the shopper.
     *
     * @param  array<string, mixed>  $json
     */
    private function respondOk(Request $request, bool $forceJson, array $json, string $resultStatus, ?string $orderNo): Response
    {
        if ($forceJson || $request->expectsJson()) {
            return response()->json($json);
        }

        return redirect()->away($this->resultUrl($resultStatus, $orderNo));
    }

    private function respondError(Request $request, bool $forceJson, int $status, string $code, string $message, Request $source): Response
    {
        if ($forceJson || $request->expectsJson()) {
            return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
        }

        return redirect()->away($this->resultUrl('failed', $this->orderNoFrom($source)));
    }

    /**
     * Trusted, server-built storefront URL — never uses shopper input, so it
     * cannot be abused as an open redirect.
     */
    private function resultUrl(string $status, ?string $orderNo): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $query = http_build_query(array_filter(['status' => $status, 'order' => $orderNo]));

        return $base.'/checkout/result?'.$query;
    }

    /** SSLCommerz echoes value_a = order_no back on every callback. */
    private function orderNoFrom(Request $request): ?string
    {
        $value = $request->input('value_a');

        return is_string($value) && $value !== '' ? $value : null;
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
