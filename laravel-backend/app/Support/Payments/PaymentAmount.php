<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;
use InvalidArgumentException;

/**
 * Resolves how much a given payment type should charge for an order. The server
 * is the sole source of truth — the client never supplies an amount.
 *
 * Every type charges the REMAINING amount (its target minus what is already
 * paid), so paying the delivery charge first and then "full" can never
 * double-charge: full then bills only total − advance_paid. Amounts floor at 0,
 * and the init endpoint rejects a zero charge ("nothing to pay").
 */
final class PaymentAmount
{
    public static function for(Order $order, string $type): Money
    {
        $paid = $order->advance_paid->toMinor();
        $total = $order->total->toMinor();

        $target = match ($type) {
            Payment::TYPE_FULL => $total,
            Payment::TYPE_PARTIAL => $order->advance_amount->toMinor(),
            // Delivery is capped at the order total (a free/edge order can't have
            // a shipping charge larger than what's owed).
            Payment::TYPE_SHIPPING => min($order->shipping_cost->toMinor(), $total),
            default => throw new InvalidArgumentException("Unknown payment type [{$type}]."),
        };

        return Money::fromMinor(max(0, $target - $paid));
    }
}
