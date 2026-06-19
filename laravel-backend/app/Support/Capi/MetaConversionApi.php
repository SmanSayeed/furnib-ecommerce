<?php

declare(strict_types=1);

namespace App\Support\Capi;

use App\Models\Order;
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

    public function purchase(Order $order, string $eventId): bool
    {
        $pixelId = $this->settings->get('marketing', 'fb_pixel_id');
        $token = $this->settings->get('marketing', 'fb_capi_token');

        if (blank($pixelId) || blank($token)) {
            return false;
        }

        $response = Http::asJson()->post(self::GRAPH.'/'.$pixelId.'/events', [
            'access_token' => (string) $token,
            'data' => [PurchasePayload::for($order, $eventId)],
        ]);

        return $response->successful();
    }
}
