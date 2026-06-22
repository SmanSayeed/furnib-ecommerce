<?php

declare(strict_types=1);

namespace App\Support\Capi;

/**
 * Customer identifiers for a Meta Conversions API event. PII (email/phone) is
 * normalized and SHA-256 hashed before it ever leaves the server; IP, user
 * agent and the first-party fbp/fbc cookies are non-secret signals passed as-is.
 * Raw PII is never stored on this object's public surface or sent in the clear.
 */
final class CapiUserData
{
    public function __construct(
        private readonly ?string $email = null,
        private readonly ?string $phone = null,
        private readonly ?string $ip = null,
        private readonly ?string $userAgent = null,
        private readonly ?string $fbp = null,
        private readonly ?string $fbc = null,
    ) {}

    /**
     * The Meta `user_data` block. Empty fields are dropped so we never send
     * hashes of empty strings (which would hurt match quality).
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'em' => self::hash(self::normalizeEmail($this->email)),
            'ph' => self::hash(self::normalizePhone($this->phone)),
            'client_ip_address' => self::clean($this->ip),
            'client_user_agent' => self::clean($this->userAgent),
            'fbp' => self::clean($this->fbp),
            'fbc' => self::clean($this->fbc),
        ], static fn (?string $v): bool => $v !== null && $v !== '');
    }

    private static function hash(?string $value): ?string
    {
        return ($value === null || $value === '') ? null : hash('sha256', $value);
    }

    private static function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return ($value === null || $value === '') ? null : $value;
    }

    private static function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = strtolower(trim($email));

        return $email === '' ? null : $email;
    }

    /**
     * Digits only, in international form. Bangladesh local numbers (01XXXXXXXXX)
     * are promoted to 8801XXXXXXXXX so they match what the Pixel hashes.
     */
    private static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = ltrim($digits, '0');
        } elseif (str_starts_with($digits, '0')) {
            $digits = '88'.$digits; // 01XXXXXXXXX -> 8801XXXXXXXXX
        }

        return $digits;
    }
}
