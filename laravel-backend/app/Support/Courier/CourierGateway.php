<?php

declare(strict_types=1);

namespace App\Support\Courier;

use App\Models\Shipment;

/**
 * Courier abstraction. Credentials live in encrypted settings and never leave
 * the server. Calling code depends only on this interface; the concrete
 * SteadFast implementation is faked in tests.
 */
interface CourierGateway
{
    /**
     * Create a consignment for the shipment and return the courier's
     * identifiers + initial status.
     *
     * @return array{consignment_id: string, tracking_code: string, status: string}
     */
    public function createConsignment(Shipment $shipment): array;

    /**
     * Fetch the current delivery status for a tracking code.
     */
    public function getStatus(string $trackingCode): string;
}
