<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Immutable money value object. All amounts are stored as integer minor units
 * (paisa). Never use floats to store or compare money.
 */
final class Money
{
    public function __construct(public readonly int $minorUnits)
    {
        if ($minorUnits < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }
    }

    public static function fromMinor(int $minorUnits): self
    {
        return new self($minorUnits);
    }

    /**
     * Build from a human display amount (e.g. 1234.56 -> 123456 minor units).
     */
    public static function fromDisplay(int|float|string $amount): self
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Money amount must be numeric.');
        }

        return new self((int) round(((float) $amount) * 100));
    }

    public function toMinor(): int
    {
        return $this->minorUnits;
    }

    public function toDisplay(): float
    {
        return $this->minorUnits / 100;
    }

    public function add(self $other): self
    {
        return new self($this->minorUnits + $other->minorUnits);
    }

    public function subtract(self $other): self
    {
        return new self($this->minorUnits - $other->minorUnits);
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits;
    }

    /**
     * Human display string with the currency symbol and NO decimals — amounts
     * are rounded to the nearest taka (e.g. 123456 minor -> "৳1,235"). Money is
     * still stored/compared in exact minor units; this only affects display.
     */
    public function format(string $symbol = '৳'): string
    {
        return $symbol.number_format($this->minorUnits / 100, 0);
    }
}
