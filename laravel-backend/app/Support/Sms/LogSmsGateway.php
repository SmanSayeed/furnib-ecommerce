<?php

declare(strict_types=1);

namespace App\Support\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Default SMS driver until a concrete BD provider (e.g. SSL Wireless / bulk SMS)
 * is wired in. It writes the message to the application log so OTPs and order
 * confirmations are observable in local/dev. This driver is intended for
 * non-production use; production must configure a real gateway.
 */
final class LogSmsGateway implements SmsGateway
{
    public function send(string $mobile, string $message): bool
    {
        Log::info('SMS dispatched (log driver)', [
            'to' => $mobile,
            'message' => $message,
        ]);

        return true;
    }
}
