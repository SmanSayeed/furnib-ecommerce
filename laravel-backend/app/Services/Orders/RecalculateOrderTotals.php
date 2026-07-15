<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;

/**
 * The single implementation of the order total invariant:
 *
 *     total = max(0, subtotal − discount + shipping_cost)
 *
 * `subtotal` is already net of per-line product discounts (snapshotted at
 * placement). `discount` is the order-level admin discount. Every path that can
 * move the total — applying a discount, changing the delivery zone — resolves it
 * here so they can never drift apart.
 */
final class RecalculateOrderTotals
{
    public static function totalMinor(Order $order): int
    {
        return max(
            0,
            $order->subtotal->toMinor()
                - $order->discount->toMinor()
                + $order->shipping_cost->toMinor(),
        );
    }
}
