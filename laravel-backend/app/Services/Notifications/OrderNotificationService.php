<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\OrderNotificationEvent;
use App\Models\Order;
use App\Support\Notifications\OrderNotificationChannel;

/**
 * Fan-out point: notify a customer about an order event across every registered
 * channel (SMS now, email later). Each channel self-guards (enablement, routing,
 * idempotency, logging), so this service just iterates — adding a channel needs
 * no change here.
 */
final class OrderNotificationService
{
    /** @var array<int, OrderNotificationChannel> */
    private array $channels;

    /**
     * @param  iterable<OrderNotificationChannel>  $channels
     */
    public function __construct(iterable $channels)
    {
        $this->channels = is_array($channels) ? $channels : iterator_to_array($channels);
    }

    public function notify(Order $order, OrderNotificationEvent $event): void
    {
        foreach ($this->channels as $channel) {
            $channel->send($order, $event);
        }
    }
}
