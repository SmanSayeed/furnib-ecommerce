<?php

declare(strict_types=1);

namespace App\Support\Courier;

/**
 * A courier whose booking needs a single delivery-area choice (e.g. RedX). The
 * area list is fetched server-side (credentials never leave the server) and the
 * chosen id is stored on the shipment's meta at booking time.
 */
interface ListsDeliveryAreas
{
    /**
     * All bookable delivery areas.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function areas(): array;
}
