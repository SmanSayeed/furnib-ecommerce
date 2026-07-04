<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Enums\OrderNotificationEvent;
use App\Models\Order;

/**
 * A delivery channel for order notifications. SMS today, email tomorrow — each
 * channel decides its own enablement and rendering; the OrderNotificationService
 * only iterates, dedups and logs. Add a channel = implement this interface and
 * tag it; no caller changes (Open/Closed).
 */
interface OrderNotificationChannel
{
    /** Stable channel key stored on the log (e.g. 'sms', 'email'). */
    public function key(): string;

    /**
     * Deliver the event notification for this order. Returns a uniform result so
     * the service can log/react channel-agnostically. Implementations must not
     * throw for ordinary delivery failures — return NotificationResult::failed().
     */
    public function send(Order $order, OrderNotificationEvent $event): NotificationResult;
}
