<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Models\Order;

/**
 * Fires the authoritative Meta Purchase conversion for an order EXACTLY ONCE,
 * when it first becomes "confirmed" — whether the admin confirmed it manually
 * or an online payment auto-confirmed it. The `marketing_purchase_sent_at`
 * stamp is the idempotency guard, so a re-confirm / duplicate payment callback
 * never double-counts the sale.
 */
final class ConfirmOrderPurchase
{
    public function __construct(private readonly SendPurchaseEvent $purchaseEvent) {}

    /**
     * @return bool True if the Purchase fired on THIS call (i.e. first confirm).
     */
    public function handle(Order $order): bool
    {
        if ($order->marketing_purchase_sent_at !== null) {
            return false;
        }

        // Non-fatal CAPI send (SendPurchaseEvent swallows its own errors). The
        // stamp is set regardless so we never retry/duplicate a flaky send.
        $this->purchaseEvent->handle($order);
        $order->forceFill(['marketing_purchase_sent_at' => now()])->save();

        return true;
    }
}
