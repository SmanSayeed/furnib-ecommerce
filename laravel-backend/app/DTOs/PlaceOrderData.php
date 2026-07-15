<?php

declare(strict_types=1);

namespace App\DTOs;

final class PlaceOrderData
{
    /**
     * @param  array<int, array{product_id:int, qty:int, price_override?:int}>  $items
     *                                                                                  `price_override` (minor units) is honoured ONLY when $source === 'admin'.
     * @param  string  $source  'storefront' (public checkout) | 'admin' (staff-created).
     *                          Every override below is IGNORED unless this is 'admin'.
     * @param  int|null  $createdBy  staff user id for an admin-created order
     * @param  int|null  $discountMinor  order-level discount (admin only)
     * @param  int|null  $shippingOverrideMinor  manual shipping override (admin only)
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
        public readonly ?string $fbp = null,
        public readonly ?string $fbc = null,
        public readonly ?string $ttp = null,
        public readonly ?string $ttclid = null,
        public readonly ?string $gaClientId = null,
        public readonly string $source = 'storefront',
        public readonly ?int $createdBy = null,
        public readonly ?int $discountMinor = null,
        public readonly ?string $discountNote = null,
        public readonly ?int $shippingOverrideMinor = null,
    ) {}

    public function isAdmin(): bool
    {
        return $this->source === 'admin';
    }
}
