<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Computes the advance amount required for a single order line, per the
 * product's advance-payment rule (MASTER-PLAN §3 / M3):
 *  - not an advance product          → 0
 *  - full                            → the whole line total (PlaceOrder also adds
 *                                       the order's shipping, so a full advance =
 *                                       the entire order total)
 *  - partial / percentage            → lineTotal × (partial_amount %), rounded to
 *                                       the nearest whole taka (no poysha, half-up)
 *  - partial / amount                → fixed partial_amount (whole-taka paisa),
 *                                       capped at lineTotal
 *  - partial / shipping              → 0 here; resolved at ORDER level (= the
 *                                       selected delivery zone's cost) in PlaceOrder,
 *                                       since shipping is per-order, not per-line.
 *
 * All amounts are integer minor units (paisa).
 */
final class AdvancePayment
{
    public static function forLine(
        Money $lineTotal,
        bool $isAdvance,
        ?string $type,
        ?string $partialType,
        ?int $partialAmount,
    ): Money {
        if (! $isAdvance || $type === null) {
            return Money::fromMinor(0);
        }

        if ($type === 'full') {
            return $lineTotal;
        }

        // type === 'partial'
        if ($partialType === 'percentage') {
            $pct = max(0, min(100, (int) $partialAmount));

            // Advances carry NO poysha — round to the nearest whole taka
            // (half-up: ৳1470.50 → ৳1471), then express back as paisa.
            $wholeTaka = (int) round(($lineTotal->toMinor() * $pct) / 10000);

            return Money::fromMinor($wholeTaka * 100);
        }

        if ($partialType === 'amount') {
            // Stored as whole-taka paisa (admin enters taka; the FormRequest
            // multiplies by 100), so it is already poysha-free.
            $fixed = max(0, (int) $partialAmount);

            return Money::fromMinor(min($fixed, $lineTotal->toMinor()));
        }

        // 'shipping' (and any unknown type) contributes nothing per-line; the
        // shipping-charge advance is added once at order level in PlaceOrder.
        return Money::fromMinor(0);
    }
}
