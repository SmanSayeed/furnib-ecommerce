<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\OrderNotificationEvent;
use App\Jobs\PushOrderToCourier;
use App\Jobs\SendOrderNotification;
use App\Models\Order;
use App\Services\Settings\SettingsService;

/**
 * Reacts to order status changes: auto-books the courier on confirm, and notifies
 * the customer (SMS now, email later) on any customer-facing status change. Both
 * are queued (never block the admin action) and self-guard on configuration, so
 * an install without courier/SMS credentials behaves exactly as before.
 */
final class OrderObserver
{
    public function __construct(private readonly SettingsService $settings) {}

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $this->autoPushToCourier($order);
        $this->notifyCustomer($order);
    }

    private function autoPushToCourier(Order $order): void
    {
        // Only the pending → confirmed transition triggers a booking.
        if ($order->status !== 'confirmed') {
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

    private function notifyCustomer(Order $order): void
    {
        $event = OrderNotificationEvent::fromStatus($order->status);

        // The notification channels self-guard (enablement, per-event toggle,
        // idempotency), so we dispatch for any customer-facing status and let the
        // channels decide — keeps the observer channel-agnostic.
        if ($event !== null) {
            SendOrderNotification::dispatch($order->id, $event->value);
        }
    }
}
