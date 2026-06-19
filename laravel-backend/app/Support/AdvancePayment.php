<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Computes the advance amount required for a single order line, per the
 * product's advance-payment rule (MASTER-PLAN §3 / M3):
 *  - not an advance product          → 0
 *  - full                            → the whole line total
 *  - partial / percentage            → lineTotal × (partial_amount %)
 *  - partial / amount                → fixed partial_amount (paisa), capped at lineTotal
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

            return Money::fromMinor(intdiv($lineTotal->toMinor() * $pct, 100));
        }

        if ($partialType === 'amount') {
            $fixed = max(0, (int) $partialAmount);

            return Money::fromMinor(min($fixed, $lineTotal->toMinor()));
        }

        return Money::fromMinor(0);
    }
}
