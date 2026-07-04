<?php

declare(strict_types=1);

namespace App\Support\Notifications\Channels;

use App\Enums\OrderNotificationEvent;
use App\Models\Order;
use App\Support\Notifications\BaseOrderNotificationChannel;
use App\Support\Notifications\MessageTemplate;
use App\Support\Notifications\NotificationResult;
use App\Support\Sms\ProvidesMessageId;
use App\Support\Sms\SmsGateway;

/**
 * Delivers order-status notifications by SMS (Automas). Content comes from the
 * admin-editable, BTRC-vettable Bangla templates in settings; enablement and
 * per-event toggles are read from settings too. Transport is the injected
 * SmsGateway, so the provider can be swapped without touching this channel.
 */
final class SmsOrderChannel extends BaseOrderNotificationChannel
{
    private ?SmsGateway $sms = null;

    public function key(): string
    {
        return 'sms';
    }

    protected function channelEnabled(): bool
    {
        return (bool) $this->settings->get('sms', 'enabled', false);
    }

    protected function eventEnabled(OrderNotificationEvent $event): bool
    {
        // Per-event default: only `Placed` is on, so a fresh install sends exactly
        // one SMS per order. The admin can switch the status events on.
        return (bool) $this->settings->get('sms', $event->toggleKey(), $event->defaultEnabled());
    }

    protected function routeFor(Order $order): string
    {
        $order->loadMissing('customer');

        return $order->customer->mobile;
    }

    protected function render(Order $order, OrderNotificationEvent $event): string
    {
        $template = (string) ($this->settings->get('sms', $event->templateKey()) ?: $event->defaultSmsTemplate());

        return MessageTemplate::render($template, MessageTemplate::forOrder($order));
    }

    protected function deliver(string $recipient, string $message): NotificationResult
    {
        $gateway = $this->gateway();

        if (! $gateway->send($recipient, $message)) {
            return NotificationResult::failed('SMS gateway rejected the message.');
        }

        // Capture the provider id (when the driver exposes it) so a later DLR can
        // be matched back to this exact message.
        $id = $gateway instanceof ProvidesMessageId ? $gateway->lastMessageId() : null;

        return NotificationResult::sent($id);
    }

    protected function provider(): string
    {
        return (string) ($this->settings->get('sms', 'provider') ?: 'automas');
    }

    /** Resolve the SMS transport lazily so a disabled channel costs nothing. */
    private function gateway(): SmsGateway
    {
        return $this->sms ??= app(SmsGateway::class);
    }
}
