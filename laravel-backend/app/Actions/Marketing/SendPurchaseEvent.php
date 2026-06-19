<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Models\Order;
use App\Support\Capi\ConversionApi;
use App\Support\Capi\PurchasePayload;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a server-side Meta Purchase event for a paid order. Non-fatal: a
 * marketing pixel failure must never break payment recording.
 */
final class SendPurchaseEvent
{
    public function __construct(private readonly ConversionApi $capi) {}

    public function handle(Order $order): void
    {
        try {
            $this->capi->purchase($order, PurchasePayload::eventId($order));
        } catch (Throwable $e) {
            Log::warning('Meta CAPI purchase failed', [
                'order_no' => $order->order_no,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
