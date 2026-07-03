<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Services\Payments\OrderPaymentReconciler;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Records the outcome of a gateway transaction against its pending Payment and
 * reconciles the order. Idempotent: a transaction already marked success is
 * returned untouched, so a duplicate IPN/return applies the money only once.
 * Acceptance requires the SERVER-VALIDATED status, a matching tran_id, and an
 * amount that reconciles with what we intended to charge.
 */
final class RecordPayment
{
    public function __construct(private readonly OrderPaymentReconciler $reconciler) {}

    /**
     * @param  array<string, mixed>  $validated  Normalized result from PaymentGateway::validatePayment().
     */
    public function handle(string $tranId, array $validated): Payment
    {
        return DB::transaction(function () use ($tranId, $validated): Payment {
            $payment = Payment::query()->where('tran_id', $tranId)->lockForUpdate()->first();

            if ($payment === null) {
                throw new DomainException('Unknown transaction.');
            }

            // Idempotency guard — already applied; do not touch the order again.
            if ($payment->status === Payment::STATUS_SUCCESS) {
                return $payment;
            }

            $statusOk = in_array(strtoupper((string) ($validated['status'] ?? '')), ['VALID', 'VALIDATED'], true);
            $tranMatches = (string) ($validated['tran_id'] ?? '') === $tranId;
            $amountMatches = Money::fromDisplay((float) ($validated['amount'] ?? 0))->equals($payment->amount);
            // SSLCommerz security check-point: verify the currency too, so a
            // VALID transaction settled in another currency can't be accepted.
            $currencyMatches = strtoupper((string) ($validated['currency'] ?? '')) === 'BDT';

            $accepted = $statusOk && $tranMatches && $amountMatches && $currencyMatches;

            $payment->forceFill([
                'val_id' => $validated['val_id'] ?? null,
                'raw_payload' => $validated,
                'status' => $accepted ? Payment::STATUS_SUCCESS : Payment::STATUS_FAILED,
                // Auto reason on rejection so support can see WHY a gateway-side
                // "success" was not accepted (never PII, never asked of the buyer).
                'note' => $accepted ? null : $this->rejectionNote($statusOk, $tranMatches, $amountMatches, $currencyMatches),
            ])->save();

            if ($payment->status === Payment::STATUS_SUCCESS) {
                $order = $payment->order()->lockForUpdate()->firstOrFail();
                $this->reconciler->reconcile($order);
            }

            return $payment;
        });
    }

    /**
     * A short, non-sensitive reason a gateway-returned transaction was NOT
     * accepted. Auto-derived from the server-side validation checks.
     */
    private function rejectionNote(bool $statusOk, bool $tranMatches, bool $amountMatches, bool $currencyMatches): string
    {
        return match (true) {
            ! $statusOk => 'Payment rejected: gateway did not return a VALID status.',
            ! $tranMatches => 'Payment rejected: transaction id mismatch.',
            ! $amountMatches => 'Payment rejected: paid amount did not match the expected amount.',
            ! $currencyMatches => 'Payment rejected: currency was not BDT.',
            default => 'Payment rejected by server-side validation.',
        };
    }
}
