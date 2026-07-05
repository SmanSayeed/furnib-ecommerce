<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Order;

/**
 * Single source of truth for "what can still be paid on this order" — the due,
 * the delivery portion, and which self-service buttons to offer. Shared by the
 * token-gated pay page and the mobile-gated success page so the two can never
 * disagree. All amounts are minor units (paisa).
 *
 * Rules:
 *  - due            = total − advance_paid (never negative)
 *  - can_pay_full   = there is anything left to pay
 *  - can_pay_shipping = a delivery charge exists, is not yet covered, AND there
 *    is a product remainder beyond it (due > shipping) — otherwise the delivery
 *    button would duplicate the full button.
 */
final class PayableState
{
    /**
     * @return array{
     *     total_minor:int, advance_paid_minor:int, due_minor:int,
     *     shipping_minor:int, free_shipping:bool,
     *     can_pay_shipping:bool, can_pay_full:bool
     * }
     */
    public static function for(Order $order): array
    {
        $total = $order->total->toMinor();
        $paid = $order->advance_paid->toMinor();
        $shipping = $order->shipping_cost->toMinor();
        $due = max(0, $total - $paid);

        return [
            'total_minor' => $total,
            'advance_paid_minor' => $paid,
            'due_minor' => $due,
            'shipping_minor' => $shipping,
            'free_shipping' => $shipping === 0,
            'can_pay_shipping' => $shipping > 0 && $paid < $shipping && $due > $shipping,
            'can_pay_full' => $due > 0,
        ];
    }
}
