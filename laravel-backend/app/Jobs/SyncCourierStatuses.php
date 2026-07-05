<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Shipment;
use App\Support\Courier\CourierManager;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * API couriers have no delivery webhook, so we poll. This scheduled job refreshes
 * the status of every in-flight shipment (not yet terminal) via that shipment's
 * own courier driver, and maps it onto our record — feeding order tracking and
 * the customer fraud/return-ratio stats. Manual couriers (no API driver) are
 * skipped; their status is updated by hand.
 */
final class SyncCourierStatuses implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 120;

    public int $uniqueFor = 1800;

    /** Statuses we never re-poll — the consignment's journey is over. */
    public const TERMINAL = ['delivered', 'partial_delivered', 'cancelled', 'returned'];

    public function handle(CourierManager $couriers): void
    {
        Shipment::query()
            ->whereNotNull('tracking_code')
            ->whereNotNull('courier_id')
            ->whereNotIn('status', self::TERMINAL)
            ->with('courierModel')
            ->each(function (Shipment $shipment) use ($couriers): void {
                $courier = $shipment->courierModel;

                if ($courier === null) {
                    return;
                }

                $driver = $couriers->driverFor($courier);

                if ($driver === null) {
                    // Manual courier — no API to poll; status is set by hand.
                    return;
                }

                try {
                    $status = $driver->getStatus((string) $shipment->tracking_code);
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
