<?php

declare(strict_types=1);

namespace App\Support\Sms;

use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Automas (asms.automas.com.bd) SMS driver. API key + sender id come from
 * encrypted settings and are sent only to Automas over HTTPS — never returned to
 * the client or logged. Bangla (non-ASCII) text is auto-sent as Unicode
 * (`smsformat=8`), as BTRC requires; ASCII (e.g. OTP) goes as-is.
 *
 * Never throws on a delivery failure — returns false so callers/order flow are
 * unaffected. See docs/sms-gateway/SMS-INTEGRATION.md.
 */
final class AutomasSmsGateway implements SmsGateway
{
    private const ENDPOINT = 'https://api.automas.com.bd/smsapiv3';

    /** Single-segment limits before the message must be flagged `type=long`. */
    private const ASCII_LIMIT = 160;

    private const UNICODE_LIMIT = 70;

    public function __construct(private readonly SettingsService $settings) {}

    public function send(string $mobile, string $message): bool
    {
        $apiKey = (string) ($this->settings->get('sms', 'api_key') ?? '');
        $sender = (string) ($this->settings->get('sms', 'sender_id') ?? '');

        if ($apiKey === '' || $sender === '') {
            Log::warning('Automas SMS not sent — credentials not configured.');

            return false;
        }

        $isUnicode = (bool) preg_match('/[^\x00-\x7F]/', $message);
        $limit = $isUnicode ? self::UNICODE_LIMIT : self::ASCII_LIMIT;

        $params = [
            'apikey' => $apiKey,
            'sender' => $sender,
            'msisdn' => $this->toMsisdn($mobile),
            'smstext' => $message,
        ];

        if ($isUnicode) {
            $params['smsformat'] = 8; // Unicode (Bangla) — BTRC mandate.
        }

        if (mb_strlen($message) > $limit) {
            $params['type'] = 'long';
        }

        try {
            $response = Http::timeout(15)->get(self::ENDPOINT, $params);
            $entry = $response->json('response.0');
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $status = is_array($entry) ? (int) ($entry['status'] ?? -1) : -1;

        if ($status !== 0) {
            Log::warning('Automas SMS rejected.', [
                'to' => $this->toMsisdn($mobile),
                'code' => $status,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Normalize our canonical +8801XXXXXXXXX to the 8801XXXXXXXXX form Automas
     * expects (strip a leading +). Any other input is passed through untouched.
     */
    private function toMsisdn(string $mobile): string
    {
        return ltrim($mobile, '+');
    }
}
