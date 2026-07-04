<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\PushOrderToCourier;
use App\Models\Order;
use App\Services\Settings\SettingsService;

/**
 * Auto-pushes a freshly confirmed order to the courier. The push is queued (never
 * blocks the admin action) and only fires when a courier is actually configured,
 * so installs without courier credentials behave exactly as before.
 */
final class OrderObserver
{
    public function __construct(private readonly SettingsService $settings) {}

    public function updated(Order $order): void
    {
        // Only the pending → confirmed transition triggers a booking.
        if (! $order->wasChanged('status') || $order->status !== 'confirmed') {
            return;
        }

        // Opt-out switch (default on) + a configured courier are both required.
        if (! (bool) $this->settings->get('steadfast', 'auto_push', true)) {
            return;
        }

        if (blank($this->settings->get('steadfast', 'api_key'))
            || blank($this->settings->get('steadfast', 'secret_key'))) {
            return;
        }

        // Already booked — nothing to do (CreateConsignment is idempotent too).
        if ($order->shipment()->whereNotNull('consignment_id')->exists()) {
            return;
        }

        PushOrderToCourier::dispatch($order->id);
    }
}
