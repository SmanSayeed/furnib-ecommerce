<?php

declare(strict_types=1);

namespace App\Support\Ga4;

use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Http;

/**
 * GA4 Measurement Protocol client. Reads the measurement id (ga4_id) + API
 * secret from encrypted marketing settings at call time and posts the event over
 * HTTPS. The API secret travels only as a query parameter to Google's endpoint
 * and is never returned to the client or logged. No-ops (returns false) when the
 * integration is not configured.
 */
final class HttpMeasurementProtocol implements MeasurementProtocol
{
    private const ENDPOINT = 'https://www.google-analytics.com/mp/collect';

    public function __construct(private readonly SettingsService $settings) {}

    public function send(Ga4Event $event): bool
    {
        $measurementId = $this->settings->get('marketing', 'ga4_id');
        $apiSecret = $this->settings->get('marketing', 'ga4_api_secret');

        if (blank($measurementId) || blank($apiSecret)) {
            return false;
        }

        $response = Http::asJson()
            ->timeout(5)
            ->post(self::ENDPOINT.'?'.http_build_query([
                'measurement_id' => (string) $measurementId,
                'api_secret' => (string) $apiSecret,
            ]), $event->toArray());

        // The MP collect endpoint returns 2xx (204) on accept; it does not
        // validate payloads (use the /debug endpoint for that).
        return $response->successful();
    }
}
