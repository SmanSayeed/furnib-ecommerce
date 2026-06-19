<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Bangladeshi mobile number normalization to canonical E.164 (`+8801XXXXXXXXX`).
 *
 * Accepts common input forms — local `01XXXXXXXXX`, `8801XXXXXXXXX`,
 * `+8801XXXXXXXXX`, `008801XXXXXXXXX`, and forms with spaces/dashes — and
 * normalizes them. The national part must be a valid BD mobile: `1[3-9]` + 8
 * digits (operator codes 013–019).
 */
final class MobileNumber
{
    private const NATIONAL_PATTERN = '/^1[3-9]\d{8}$/';

    private function __construct(public readonly string $e164) {}

    public static function fromInput(string $input): self
    {
        $digits = preg_replace('/\D/', '', $input) ?? '';

        // Strip an international "00" prefix (e.g. 008801…).
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '880')) {
            $national = substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $national = substr($digits, 1);
        } else {
            $national = $digits;
        }

        if (! preg_match(self::NATIONAL_PATTERN, $national)) {
            throw new InvalidArgumentException("Invalid Bangladeshi mobile number: {$input}");
        }

        return new self('+880'.$national);
    }

    public static function isValid(string $input): bool
    {
        try {
            self::fromInput($input);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function normalize(string $input): string
    {
        return self::fromInput($input)->e164;
    }

    public function __toString(): string
    {
        return $this->e164;
    }
}
