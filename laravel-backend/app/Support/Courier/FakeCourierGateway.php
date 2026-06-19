<?php

declare(strict_types=1);

namespace App\Support\Courier;

use App\Models\Shipment;

/**
 * In-memory courier gateway for tests. Returns scripted consignment data and
 * tracking status without any network call.
 */
final class FakeCourierGateway implements CourierGateway
{
    /** @var array<int, int> shipment ids passed to createConsignment */
    public array $created = [];

    public string $status = 'in_review';

    public function createConsignment(Shipment $shipment): array
    {
        $this->created[] = $shipment->id;

        return [
            'consignment_id' => 'CN-'.$shipment->id,
            'tracking_code' => 'TRK'.str_pad((string) $shipment->id, 8, '0', STR_PAD_LEFT),
            'status' => 'in_review',
        ];
    }

    public function getStatus(string $trackingCode): string
    {
        return $this->status;
    }
}
