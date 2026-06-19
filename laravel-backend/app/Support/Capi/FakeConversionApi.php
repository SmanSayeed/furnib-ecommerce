<?php

declare(strict_types=1);

namespace App\Support\Capi;

use App\Models\Order;

/**
 * In-memory Conversions API for tests. Records each purchase event without any
 * network call.
 */
final class FakeConversionApi implements ConversionApi
{
    /** @var array<int, array{order_no: string, event_id: string}> */
    public array $purchases = [];

    public function purchase(Order $order, string $eventId): bool
    {
        $this->purchases[] = ['order_no' => $order->order_no, 'event_id' => $eventId];

        return true;
    }
}
