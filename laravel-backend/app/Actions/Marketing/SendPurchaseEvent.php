<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Models\Order;
use App\Support\Capi\CapiEvents;
use App\Support\Capi\CapiUserData;
use App\Support\Capi\ConversionApi;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a server-side Meta Purchase event for an order. Non-fatal: a marketing
 * pixel failure must never break order placement or payment recording. The
 * event id is deterministic (`purchase.<order_no>`) so the browser Pixel and
 * this server copy are de-duplicated by Meta — firing it from both COD
 * placement and online-payment success counts the purchase exactly once.
 */
final class SendPurchaseEvent
{
    public function __construct(private readonly ConversionApi $capi) {}

    public function handle(Order $order, ?CapiUserData $user = null, ?string $url = null): void
    {
        try {
            $user ??= new CapiUserData(
                email: $order->customer?->email,
                phone: $order->customer?->mobile,
                ip: $order->customer_ip,
                userAgent: $order->user_agent,
            );

            $this->capi->send(CapiEvents::purchase($order, $user, $url));
        } catch (Throwable $e) {
            Log::warning('Meta CAPI purchase failed', [
                'order_no' => $order->order_no,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
