<?php

declare(strict_types=1);

namespace App\Actions\Shipments;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Support\Courier\CourierManager;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Books (or returns the existing) shipment for an order with a chosen courier.
 * Idempotent per order: once an API consignment exists it is returned untouched.
 * An API courier is pushed to its provider; a manual courier is recorded only
 * (its name still prints on the label). The COD amount is the remaining balance
 * (total minus what was already paid), computed server-side.
 */
final class CreateConsignment
{
    public function __construct(private readonly CourierManager $manager) {}

    /**
     * @param  array<string, mixed>  $meta  Booking-time courier metadata (e.g. the
     *                                      RedX delivery area, or the Pathao
     *                                      city/zone/area). Ignored by couriers
     *                                      that need none (Steadfast, manual).
     */
    public function handle(Order $order, Courier $courier, ?string $note = null, array $meta = []): Shipment
    {
        $order->loadMissing('customer');

        // All-or-nothing. The shipment row has to exist before the API call (the
        // adapters read the recipient/COD fields off it), but if the provider then
        // rejects us, that row must NOT survive — otherwise the order shows a
        // "shipment" the courier never received, and every downstream check that
        // asks "is this booked?" answers yes for a booking that failed.
        return DB::transaction(function () use ($order, $courier, $note, $meta): Shipment {
            $shipment = Shipment::query()->firstOrNew(['order_id' => $order->id]);

            // Already booked with an API consignment — never create a duplicate.
            if (filled($shipment->consignment_id)) {
                return $shipment;
            }

            $codMinor = max(0, $order->total->toMinor() - $order->advance_paid->toMinor());

            $shipment->fill([
                'courier_id' => $courier->id,
                'courier' => $courier->name,   // name snapshot: survives courier deletion
                'recipient_name' => (string) ($order->customer->name ?? 'Customer'),
                'recipient_phone' => (string) ($order->customer->mobile ?? ''),
                'recipient_address' => $order->address,
                'cod_amount' => Money::fromMinor($codMinor),
                'status' => Shipment::STATUS_PENDING,
                'note' => $note,
                'meta' => $meta === [] ? null : $meta,
            ]);
            $shipment->save();

            // Manual (or unregistered) courier: recorded only — no API call. The admin
            // books it by hand and can fill the consignment/tracking + status later.
            $driver = $this->manager->driverFor($courier);

            if ($driver === null) {
                return $shipment;
            }

            // Throws CourierException on a rejection — which rolls the row back.
            $result = $driver->createConsignment($shipment);

            $shipment->update([
                'consignment_id' => $result['consignment_id'],
                'tracking_code' => $result['tracking_code'],
                'status' => $result['status'],
                'raw_payload' => $result,
            ]);

            return $shipment;
        });
    }
}
