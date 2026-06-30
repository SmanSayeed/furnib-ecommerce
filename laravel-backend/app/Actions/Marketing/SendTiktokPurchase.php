<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Models\Order;
use App\Support\Tiktok\EventsApi;
use App\Support\Tiktok\TiktokEvents;
use App\Support\Tiktok\TiktokUserData;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a server-side TikTok CompletePayment for an order. Non-fatal: a
 * marketing failure must never break order confirmation. The event id is
 * deterministic (`purchase.<order_no>`) so the browser Pixel and this server
 * copy are de-duplicated by TikTok.
 */
final class SendTiktokPurchase
{
    public function __construct(private readonly EventsApi $tiktok) {}

    public function handle(Order $order, ?string $url = null): void
    {
        try {
            $order->loadMissing('customer');

            $user = new TiktokUserData(
                email: $order->customer?->email,
                phone: $order->customer?->mobile,
                externalId: (string) $order->customer_id,
                ip: $order->customer_ip,
                userAgent: $order->user_agent,
                ttp: $order->ttp,
                ttclid: $order->ttclid,
            );

            $this->tiktok->send(TiktokEvents::purchase($order, $user, $url));
        } catch (Throwable $e) {
            Log::warning('TikTok purchase failed', [
                'order_no' => $order->order_no,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
