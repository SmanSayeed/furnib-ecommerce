<?php

declare(strict_types=1);

namespace App\Support\Tiktok;

use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Http;

/**
 * TikTok Events API (v1.3) client. Reads the pixel code + access token from
 * encrypted marketing settings at call time and posts the event over HTTPS. The
 * token travels only in the `Access-Token` header (never the URL or body) and is
 * never returned to the client or logged. No-ops (returns false) when the
 * integration is not configured.
 */
final class HttpEventsApi implements EventsApi
{
    private const ENDPOINT = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';

    public function __construct(private readonly SettingsService $settings) {}

    public function send(TiktokEvent $event): bool
    {
        $pixelCode = $this->settings->get('marketing', 'tiktok_pixel_id');
        $token = $this->settings->get('marketing', 'tiktok_access_token');

        if (blank($pixelCode) || blank($token)) {
            return false;
        }

        $payload = [
            'event_source' => 'web',
            'event_source_id' => (string) $pixelCode,
            'data' => [$event->toArray()],
        ];

        // Optional test-event code (TikTok Events Manager → Test Events) for QA.
        $testCode = $this->settings->get('marketing', 'tiktok_test_event_code');
        if (filled($testCode)) {
            $payload['test_event_code'] = (string) $testCode;
        }

        $response = Http::asJson()
            ->withHeaders(['Access-Token' => (string) $token])
            ->timeout(5)
            ->post(self::ENDPOINT, $payload);

        // TikTok returns HTTP 200 with a body `code` of 0 on success.
        return $response->successful() && (int) $response->json('code', -1) === 0;
    }
}
