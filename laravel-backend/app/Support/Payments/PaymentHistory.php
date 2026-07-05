<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Order;
use App\Models\Payment;

/**
 * Customer-facing payment history for an order: the successful and still-pending
 * rows, newest first, with only non-sensitive fields (never the encrypted
 * gateway payload, val_id, or tran_id). Failed/cancelled attempts are hidden
 * from the shopper — the admin ledger keeps the full record.
 */
final class PaymentHistory
{
    /**
     * @return array<int, array{type:string, direction:string, status:string, amount:string, amount_minor:int, gateway:string, date:?string}>
     */
    public static function forOrder(Order $order): array
    {
        return $order->payments()
            ->whereIn('status', [Payment::STATUS_SUCCESS, Payment::STATUS_PENDING])
            ->latest()
            ->get()
            ->map(fn (Payment $p): array => [
                'type' => $p->type,
                'direction' => (string) ($p->direction ?? Payment::DIRECTION_CREDIT),
                'status' => $p->status,
                'amount' => $p->amount->format('Tk '),
                'amount_minor' => $p->amount->toMinor(),
                'gateway' => (string) $p->gateway,
                'date' => $p->created_at?->toDateTimeString(),
            ])
            ->all();
    }
}
