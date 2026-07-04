<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Actions\Payments\RecordPayment;
use App\Models\Payment;
use App\Services\Settings\SettingsService;
use App\Support\Payments\PaymentGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Safety net for the rare "bank captured the money but our callback AND IPN were
 * both lost" case (a false-negative). Sweeps still-pending gateway payments,
 * re-queries the gateway by our tran_id, and records the outcome.
 *
 * Recording delegates to RecordPayment (idempotent, server-side validated,
 * DB-transaction + lockForUpdate), so a payment that also arrives via a late IPN
 * is never double-applied. The core money-record stays synchronous; this sweep
 * is pure resilience and runs on the scheduler.
 */
final class PendingPaymentReconciler
{
    /** Give the normal callback/IPN this long to arrive before we sweep (minutes). */
    private const GRACE_MINUTES = 5;

    /** Stop chasing a pending payment after this age — treat as truly abandoned (hours). */
    private const WINDOW_HOURS = 72;

    /** Gateway statuses that mean "money moved" — hand to RecordPayment to validate + apply. */
    private const SETTLED = ['VALID', 'VALIDATED'];

    /** Terminal negative statuses — resolve the pending row to failed. */
    private const DEAD = ['FAILED', 'CANCELLED', 'EXPIRED', 'UNATTEMPTED'];

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPayment $recordPayment,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return array{swept: int, recovered: int, failed: int, still_pending: int}
     */
    public function sweep(): array
    {
        $empty = ['swept' => 0, 'recovered' => 0, 'failed' => 0, 'still_pending' => 0];

        // Nothing to query without gateway credentials — skip cleanly (no error
        // spam) so `schedule:run` is a no-op until SSLCommerz is configured. Reads
        // the ACTIVE mode's credentials (sandbox/live), with a legacy fallback.
        $mode = (bool) $this->settings->get('sslcommerz', 'sandbox', true) ? 'sandbox' : 'live';
        $storeId = $this->settings->get('sslcommerz', $mode.'_store_id')
            ?? $this->settings->get('sslcommerz', 'store_id');
        $storePassword = $this->settings->get('sslcommerz', $mode.'_store_passwd')
            ?? $this->settings->get('sslcommerz', 'store_passwd');

        if (blank($storeId) || blank($storePassword)) {
            return $empty;
        }

        $payments = Payment::query()
            ->where('gateway', 'sslcommerz')
            ->where('status', Payment::STATUS_PENDING)
            ->where('created_at', '<=', Carbon::now()->subMinutes(self::GRACE_MINUTES))
            ->where('created_at', '>=', Carbon::now()->subHours(self::WINDOW_HOURS))
            ->get();

        $recovered = 0;
        $failed = 0;
        $stillPending = 0;

        foreach ($payments as $payment) {
            try {
                $result = $this->gateway->queryTransaction($payment->tran_id);
            } catch (Throwable $e) {
                // Gateway query API is down — leave the row pending for the next
                // sweep. Never mark failed on a transport error (that would be the
                // very false-negative we are guarding against).
                report($e);
                $stillPending++;

                continue;
            }

            if ($result === null) {
                $stillPending++;

                continue;
            }

            $status = strtoupper((string) ($result['status'] ?? ''));

            if (in_array($status, self::SETTLED, true)) {
                // RecordPayment re-runs the full acceptance gate (amount, currency,
                // tran_id) before it credits the ledger, then reconciles the order.
                $recorded = $this->recordPayment->handle($payment->tran_id, $result);

                if ($recorded->status === Payment::STATUS_SUCCESS) {
                    $recovered++;
                    Log::info('Payment reconciled from gateway query.', [
                        'tran_id' => $payment->tran_id,
                        'order_id' => $payment->order_id,
                    ]);
                } else {
                    $failed++;
                }

                continue;
            }

            if (in_array($status, self::DEAD, true)) {
                $payment->update([
                    'status' => Payment::STATUS_FAILED,
                    'note' => 'Reconciliation: gateway reported '.$status.' with no successful callback.',
                ]);
                $failed++;

                continue;
            }

            // Anything else (PENDING/PROCESSING/…) — still in flight, try next sweep.
            $stillPending++;
        }

        return [
            'swept' => $payments->count(),
            'recovered' => $recovered,
            'failed' => $failed,
            'still_pending' => $stillPending,
        ];
    }
}
