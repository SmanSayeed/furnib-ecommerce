<?php

declare(strict_types=1);

namespace App\DTOs;

final class PlaceOrderData
{
    /**
     * @param  array<int, array{product_id:int, qty:int}>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly string $customerMobile,
        public readonly ?string $customerName,
        public readonly ?string $customerEmail,
        public readonly ?int $shippingZoneId,
        public readonly string $address,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $notes = null,
    ) {}
}
