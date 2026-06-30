<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Models\Order;
use App\Support\Ga4\Ga4Events;
use App\Support\Ga4\MeasurementProtocol;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a server-side GA4 `purchase` (Measurement Protocol) for an order.
 * Non-fatal: a marketing failure must never break order confirmation.
 *
 * GA4 requires a client_id. We use the `_ga` client id captured at checkout so
 * the conversion joins the customer's GA4 session; if it is missing (no GA
 * cookie at the time), we fall back to a stable per-order id so the sale is
 * still recorded (as a standalone client).
 */
final class SendGa4Purchase
{
    public function __construct(private readonly MeasurementProtocol $ga4) {}

    public function handle(Order $order): void
    {
        try {
            $clientId = $order->ga_client_id;

            if ($clientId === null || $clientId === '') {
                $clientId = 'srv.'.$order->order_no;
            }

            $this->ga4->send(Ga4Events::purchase($order, $clientId));
        } catch (Throwable $e) {
            Log::warning('GA4 purchase failed', [
                'order_no' => $order->order_no,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
