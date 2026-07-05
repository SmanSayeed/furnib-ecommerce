<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Shipments\CreateConsignment;
use App\Models\Courier;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Books the courier consignment for a confirmed order, off the request cycle.
 * Retryable (the courier API may be briefly unavailable) and unique per order so
 * a burst of confirms never books twice. CreateConsignment is itself idempotent,
 * so a retry after a partial success still results in exactly one consignment.
 */
final class PushOrderToCourier implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> escalating backoff between retries (seconds). */
    public array $backoff = [60, 300, 900];

    public int $uniqueFor = 1800;

    public function __construct(
        public readonly int $orderId,
        public readonly int $courierId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    public function handle(CreateConsignment $createConsignment): void
    {
        $order = Order::query()->find($this->orderId);
        $courier = Courier::query()->find($this->courierId);

        if ($order === null || $courier === null) {
            return;
        }

        $createConsignment->handle($order, $courier);
    }
}
