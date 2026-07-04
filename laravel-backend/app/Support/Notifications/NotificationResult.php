<?php

declare(strict_types=1);

namespace App\Support\Notifications;

/**
 * Uniform outcome returned by every notification channel, so the orchestrating
 * service logs and reacts the same way regardless of channel (SMS, email, …).
 *
 * - `skipped`  → channel/event disabled, or no route (mobile/email) — do NOT log.
 * - `sent`     → delivered to the provider; log with the provider id.
 * - `failed`   → attempted but the provider rejected it; log with the reason/code.
 */
final class NotificationResult
{
    private function __construct(
        public readonly string $status,   // 'skipped' | 'sent' | 'failed'
        public readonly ?string $providerMessageId = null,
        public readonly ?string $code = null,
        public readonly ?string $error = null,
    ) {}

    public static function skipped(): self
    {
        return new self('skipped');
    }

    public static function sent(?string $providerMessageId = null, ?string $code = null): self
    {
        return new self('sent', $providerMessageId, $code);
    }

    public static function failed(string $error, ?string $code = null): self
    {
        return new self('failed', null, $code, $error);
    }

    public function wasAttempted(): bool
    {
        return $this->status !== 'skipped';
    }

    public function ok(): bool
    {
        return $this->status === 'sent';
    }
}
