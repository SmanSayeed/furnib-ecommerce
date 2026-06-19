<?php

declare(strict_types=1);

namespace App\Actions\Shipments;

use App\Models\Order;
use App\Models\Shipment;
use App\Support\Courier\CourierGateway;
use App\Support\Money;

/**
 * Creates (or returns the existing) courier consignment for an order. Idempotent
 * per order: once a consignment exists it is returned untouched. The COD amount
 * is the remaining balance (total minus what was already paid), computed
 * server-side.
 */
final class CreateConsignment
{
    public function __construct(private readonly CourierGateway $courier) {}

    public function handle(Order $order, ?string $note = null): Shipment
    {
        $order->loadMissing('customer');

        $shipment = Shipment::query()->firstOrNew(['order_id' => $order->id]);

        // Already booked — do not create a duplicate consignment.
        if (filled($shipment->consignment_id)) {
            return $shipment;
        }

        $codMinor = max(0, $order->total->toMinor() - $order->advance_paid->toMinor());

        $shipment->fill([
            'courier' => 'steadfast',
            'recipient_name' => (string) ($order->customer->name ?? 'Customer'),
            'recipient_phone' => (string) ($order->customer->mobile ?? ''),
            'recipient_address' => $order->address,
            'cod_amount' => Money::fromMinor($codMinor),
            'status' => Shipment::STATUS_PENDING,
            'note' => $note,
        ]);
        $shipment->save();

        $result = $this->courier->createConsignment($shipment);

        $shipment->update([
            'consignment_id' => $result['consignment_id'],
            'tracking_code' => $result['tracking_code'],
            'status' => $result['status'],
            'raw_payload' => $result,
        ]);

        return $shipment;
    }
}
