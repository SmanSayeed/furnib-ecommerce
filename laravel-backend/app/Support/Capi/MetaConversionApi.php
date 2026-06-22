<?php

declare(strict_types=1);

namespace App\Support\Capi;

use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Http;

/**
 * Meta Conversions API client. Reads the pixel id + access token from encrypted
 * marketing settings at call time and posts the event to the Graph API over
 * HTTPS. The token is never returned to the client or logged. No-ops (returns
 * false) when the integration is not configured.
 */
final class MetaConversionApi implements ConversionApi
{
    private const GRAPH = 'https://graph.facebook.com/v19.0';

    public function __construct(private readonly SettingsService $settings) {}

    public function send(CapiEvent $event): bool
    {
        $pixelId = $this->settings->get('marketing', 'fb_pixel_id');
        $token = $this->settings->get('marketing', 'fb_capi_token');

        if (blank($pixelId) || blank($token)) {
            return false;
        }

        // Token travels in the request BODY (never the URL) so it cannot leak
        // into access logs or referrers.
        $payload = [
            'access_token' => (string) $token,
            'data' => [$event->toArray()],
        ];

        // Optional test-event code (Events Manager → Test Events) for QA.
        $testCode = $this->settings->get('marketing', 'fb_test_event_code');
        if (filled($testCode)) {
            $payload['test_event_code'] = (string) $testCode;
        }

        $response = Http::asJson()
            ->timeout(5)
            ->post(self::GRAPH.'/'.$pixelId.'/events', $payload);

        return $response->successful();
    }
}
