<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Enums\OrderNotificationEvent;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Services\Settings\SettingsService;

/**
 * Template-method base for order notification channels. Handles the parts every
 * channel shares — enablement, routing, idempotency and logging — once, and
 * defers only the channel-specific rendering + delivery to subclasses. A new
 * channel (email, WhatsApp, …) implements the few abstract hooks and is done.
 */
abstract class BaseOrderNotificationChannel implements OrderNotificationChannel
{
    public function __construct(protected readonly SettingsService $settings) {}

    final public function send(Order $order, OrderNotificationEvent $event): NotificationResult
    {
        if (! $this->channelEnabled() || ! $this->eventEnabled($event)) {
            return NotificationResult::skipped();
        }

        $recipient = $this->routeFor($order);

        if ($recipient === null || $recipient === '') {
            return NotificationResult::skipped();
        }

        // Idempotency — never send the same order+event+channel twice.
        if ($this->alreadySent($order->id, $event)) {
            return NotificationResult::skipped();
        }

        $message = $this->render($order, $event);
        $result = $this->deliver($recipient, $message);

        if ($result->wasAttempted()) {
            $this->log($order, $event, $recipient, $message, $result);
        }

        return $result;
    }

    /** Whether the whole channel is switched on in settings. */
    abstract protected function channelEnabled(): bool;

    /** Whether this specific event is enabled for the channel. */
    abstract protected function eventEnabled(OrderNotificationEvent $event): bool;

    /** The destination address (mobile / email) for this order, or null to skip. */
    abstract protected function routeFor(Order $order): ?string;

    /** The rendered message body for this event. */
    abstract protected function render(Order $order, OrderNotificationEvent $event): string;

    /** Hand the rendered message to the provider. Must not throw. */
    abstract protected function deliver(string $recipient, string $message): NotificationResult;

    /** Provider name recorded on the log (e.g. 'automas'). */
    protected function provider(): ?string
    {
        return null;
    }

    protected function alreadySent(int $orderId, OrderNotificationEvent $event): bool
    {
        return NotificationLog::query()
            ->where('order_id', $orderId)
            ->where('event', $event->value)
            ->where('channel', $this->key())
            ->where('status', NotificationLog::STATUS_SENT)
            ->exists();
    }

    protected function log(Order $order, OrderNotificationEvent $event, string $recipient, string $message, NotificationResult $result): void
    {
        // updateOrCreate so a prior failed attempt is upgraded on a later retry,
        // honouring the (order_id, event, channel) unique key.
        NotificationLog::query()->updateOrCreate(
            ['order_id' => $order->id, 'event' => $event->value, 'channel' => $this->key()],
            [
                'recipient' => $recipient,
                'message' => $message,
                'provider' => $this->provider(),
                'provider_message_id' => $result->providerMessageId,
                'status' => $result->ok() ? NotificationLog::STATUS_SENT : NotificationLog::STATUS_FAILED,
                'status_code' => $result->code,
                'error' => $result->error,
            ],
        );
    }
}
