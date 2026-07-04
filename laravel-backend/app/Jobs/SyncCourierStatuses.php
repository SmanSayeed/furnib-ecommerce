<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Shipment;
use App\Support\Courier\CourierGateway;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Steadfast has no delivery webhook, so we poll. This scheduled job refreshes the
 * courier status of every shipment that is still in flight (not yet in a terminal
 * state) and maps it onto our record — feeding both order tracking and the
 * customer fraud/return-ratio stats.
 */
final class SyncCourierStatuses implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 120;

    public int $uniqueFor = 1800;

    /** Statuses we never re-poll — the consignment's journey is over. */
    public const TERMINAL = ['delivered', 'partial_delivered', 'cancelled', 'returned'];

    public function handle(CourierGateway $courier): void
    {
        Shipment::query()
            ->whereNotNull('tracking_code')
            ->whereNotIn('status', self::TERMINAL)
            ->each(function (Shipment $shipment) use ($courier): void {
                try {
                    $status = $courier->getStatus((string) $shipment->tracking_code);
                } catch (Throwable $e) {
                    // Transient courier error — leave the status, retry next run.
                    report($e);

                    return;
                }

                if ($status !== '' && $status !== $shipment->status) {
                    $shipment->update(['status' => $status]);
                }
            });
    }
}
