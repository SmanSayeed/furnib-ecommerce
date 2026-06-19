<?php

declare(strict_types=1);

namespace App\Support\Capi;

use App\Models\Order;

/**
 * Maps an order onto a Meta Conversions API Purchase event payload.
 */
final class PurchasePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Order $order, string $eventId): array
    {
        return [
            'event_name' => 'Purchase',
            'event_id' => $eventId,
            'action_source' => 'website',
            'custom_data' => [
                'currency' => 'BDT',
                'value' => number_format($order->total->toDisplay(), 2, '.', ''),
                'order_id' => $order->order_no,
            ],
        ];
    }

    /**
     * Deterministic event id shared with the browser Pixel for de-duplication.
     */
    public static function eventId(Order $order): string
    {
        return 'purchase.'.$order->order_no;
    }
}
