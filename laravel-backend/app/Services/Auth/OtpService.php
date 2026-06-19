<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\OtpCode;
use App\Support\MobileNumber;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Issues and verifies one-time login codes for customer mobile auth. Codes are
 * hashed at rest, single-use, short-lived, and locked out after too many wrong
 * attempts. A per-mobile cooldown blocks SMS-bombing a single number even from
 * rotating IPs (the route-level throttle covers per-IP flooding).
 */
final class OtpService
{
    public const CODE_LENGTH = 6;

    public const EXPIRY_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Generate a fresh code for the mobile and return the PLAINTEXT code so the
     * caller can deliver it via SMS. Only the hash is persisted. Throws a 429
     * when a code was already issued within the cooldown window.
     */
    public function issue(string $mobile): string
    {
        $normalized = MobileNumber::normalize($mobile);

        $recentlyIssued = OtpCode::query()
            ->where('mobile', $normalized)
            ->where('created_at', '>', now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))
            ->exists();

        if ($recentlyIssued) {
            throw new TooManyRequestsHttpException(
                self::RESEND_COOLDOWN_SECONDS,
                'Please wait before requesting another code.',
            );
        }

        // Only one active code per mobile.
        OtpCode::query()->where('mobile', $normalized)->delete();

        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        OtpCode::query()->create([
            'mobile' => $normalized,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            'attempts' => 0,
        ]);

        return $code;
    }

    /**
     * Verify a submitted code. Consumes the code on success (single-use).
     * Returns false for unknown / expired / locked-out / mismatched codes,
     * incrementing the attempt counter on a wrong guess.
     */
    public function verify(string $mobile, string $code): bool
    {
        $normalized = MobileNumber::normalize($mobile);

        $otp = OtpCode::query()->where('mobile', $normalized)->latest('id')->first();

        if ($otp === null) {
            return false;
        }

        if ($otp->expires_at->isPast()) {
            $otp->delete();

            return false;
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($code, $otp->code)) {
            $otp->increment('attempts');

            return false;
        }

        $otp->delete();

        return true;
    }
}
