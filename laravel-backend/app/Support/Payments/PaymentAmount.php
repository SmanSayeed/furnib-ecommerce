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
 */
final class PaymentAmount
{
    public static function for(Order $order, string $type): Money
    {
        return match ($type) {
            Payment::TYPE_FULL => $order->total,
            Payment::TYPE_PARTIAL => $order->advance_paid,
            Payment::TYPE_SHIPPING => $order->shipping_cost,
            default => throw new InvalidArgumentException("Unknown payment type [{$type}]."),
        };
    }
}
