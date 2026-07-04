<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\OrderNotificationEvent;
use App\Models\Order;
use App\Services\Notifications\OrderNotificationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends the customer notification(s) for an order event off the request cycle.
 * Unique per order+event so a burst of updates never queues duplicates; the
 * channels' own idempotency guard is the final backstop.
 */
final class SendOrderNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $orderId,
        public readonly string $event,
    ) {}

    public function uniqueId(): string
    {
        return $this->orderId.'-'.$this->event;
    }

    public function handle(OrderNotificationService $notifications): void
    {
        $order = Order::query()->find($this->orderId);
        $event = OrderNotificationEvent::tryFrom($this->event);

        if ($order === null || $event === null) {
            return;
        }

        $notifications->notify($order, $event);
    }
}
