<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;

/**
 * Single source of truth for how much an order has been paid. Recomputes
 * `orders.advance_paid` from the payments ledger — the sum of successful credits
 * (gateway payments + manual "payment received") minus successful debits (manual
 * refunds/reductions), floored at zero — and derives the payment_status.
 *
 * Payment NEVER auto-confirms an order: even a fully paid order stays whatever
 * status it is until an admin confirms it (business rule: payment ≠ confirmation).
 */
final class OrderPaymentReconciler
{
    public function reconcile(Order $order): void
    {
        $paid = $order->payments()->where('status', Payment::STATUS_SUCCESS);

        $creditMinor = (int) (clone $paid)->where('direction', Payment::DIRECTION_CREDIT)->sum('amount');
        $debitMinor = (int) (clone $paid)->where('direction', Payment::DIRECTION_DEBIT)->sum('amount');

        $paidMinor = max(0, $creditMinor - $debitMinor);
        $totalMinor = $order->total->toMinor();

        $paymentStatus = match (true) {
            $paidMinor >= $totalMinor && $totalMinor > 0 => 'paid',
            $paidMinor > 0 => 'partial',
            default => 'unpaid',
        };

        $order->update([
            'advance_paid' => Money::fromMinor($paidMinor),
            'payment_status' => $paymentStatus,
        ]);
    }
}
