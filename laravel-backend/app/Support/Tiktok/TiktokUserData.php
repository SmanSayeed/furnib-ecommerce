<?php

declare(strict_types=1);

namespace App\Support\Tiktok;

/**
 * Customer identifiers for a TikTok Events API event. PII (email/phone/external
 * id) is normalized and SHA-256 hashed before it leaves the server; ip, user
 * agent and the first-party ttp/ttclid signals are non-secret and sent as-is.
 *
 * TikTok normalization differs from Meta's: the phone is kept in full E.164
 * form WITH the leading "+" before hashing (Meta drops the "+").
 */
final class TiktokUserData
{
    public function __construct(
        private readonly ?string $email = null,
        private readonly ?string $phone = null,
        private readonly ?string $externalId = null,
        private readonly ?string $ip = null,
        private readonly ?string $userAgent = null,
        private readonly ?string $ttp = null,
        private readonly ?string $ttclid = null,
    ) {}

    /**
     * The TikTok `user` block. Empty fields are dropped so we never send hashes
     * of empty strings (which would hurt match quality).
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'email' => self::hash(self::normalizeEmail($this->email)),
            'phone' => self::hash(self::normalizePhone($this->phone)),
            'external_id' => self::hash(self::clean($this->externalId)),
            'ip' => self::clean($this->ip),
            'user_agent' => self::clean($this->userAgent),
            'ttp' => self::clean($this->ttp),
            'ttclid' => self::clean($this->ttclid),
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
     * Full E.164 with the leading "+" (TikTok's requirement). Bangladesh local
     * numbers (01XXXXXXXXX) are promoted to +8801XXXXXXXXX.
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

        return '+'.$digits;
    }
}
