<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Order;
use App\Models\Payment;
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

            $payment->forceFill([
                'val_id' => $validated['val_id'] ?? null,
                'raw_payload' => $validated,
                'status' => ($statusOk && $tranMatches && $amountMatches && $currencyMatches)
                    ? Payment::STATUS_SUCCESS
                    : Payment::STATUS_FAILED,
            ])->save();

            if ($payment->status === Payment::STATUS_SUCCESS) {
                $this->reconcileOrder($payment);
            }

            return $payment;
        });
    }

    private function reconcileOrder(Payment $payment): void
    {
        $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

        $paidMinor = (int) $order->payments()->where('status', Payment::STATUS_SUCCESS)->sum('amount');
        $totalMinor = $order->total->toMinor();

        $paymentStatus = match (true) {
            $paidMinor >= $totalMinor => 'paid',
            $paidMinor > 0 => 'partial',
            default => 'unpaid',
        };

        // Record the money only — never auto-confirm. Even a fully paid order
        // stays `pending` until an admin manually confirms it (business rule:
        // payment ≠ confirmation). The Purchase conversion already fired once at
        // order placement (Api\CheckoutController), so nothing marketing here.
        $order->update([
            'advance_paid' => Money::fromMinor($paidMinor),
            'payment_status' => $paymentStatus,
        ]);
    }
}
