<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Services\Settings\SettingsService;

/**
 * Applies the dynamic SMTP settings (stored encrypted in DB) onto the runtime
 * mail config so transactional mail uses the client-configured transport. The
 * secret password is decrypted only here, server-side, at send time.
 */
final class MailConfigurator
{
    public const GROUP = 'smtp';

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Returns true when SMTP is configured and was applied as the active mailer.
     */
    public function apply(): bool
    {
        $host = $this->settings->get(self::GROUP, 'host');

        if (blank($host)) {
            return false;
        }

        $fromAddress = (string) ($this->settings->get(self::GROUP, 'from_address') ?: config('mail.from.address'));
        $fromName = (string) ($this->settings->get(self::GROUP, 'from_name') ?: config('mail.from.name'));

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => (string) $host,
            'mail.mailers.smtp.port' => (int) ($this->settings->get(self::GROUP, 'port') ?: 587),
            'mail.mailers.smtp.username' => (string) $this->settings->get(self::GROUP, 'username'),
            'mail.mailers.smtp.password' => (string) $this->settings->get(self::GROUP, 'password'),
            'mail.mailers.smtp.scheme' => $this->settings->get(self::GROUP, 'encryption') ?: null,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);

        return true;
    }
}
