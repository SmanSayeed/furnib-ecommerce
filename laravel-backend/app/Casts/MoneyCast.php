<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Casts an integer minor-unit column to/from a {@see Money} value object.
 *
 * - get: stored int -> Money
 * - set: Money -> int (minor units); numeric -> treated as display amount
 *
 * @implements CastsAttributes<Money|null, Money|int|float|string|null>
 */
final class MoneyCast implements CastsAttributes
{
    /** @param array<string,mixed> $attributes */
    public function get($model, string $key, $value, array $attributes): ?Money
    {
        return $value === null ? null : Money::fromMinor((int) $value);
    }

    /** @param array<string,mixed> $attributes */
    public function set($model, string $key, $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            return $value->toMinor();
        }

        return Money::fromDisplay($value)->toMinor();
    }
}
