<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;

class MarketingController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Public analytics IDs for the storefront. Only the client-safe public IDs
     * are exposed — the Meta CAPI token is a server-side secret and is never
     * returned here (explicit whitelist, independent of the secret flag).
     */
    public function index(): JsonResponse
    {
        $m = $this->settings->toArray('marketing');

        return response()->json([
            'data' => [
                'gtm_id' => $m['gtm_id'] ?? null,
                'ga4_id' => $m['ga4_id'] ?? null,
                'fb_pixel_id' => $m['fb_pixel_id'] ?? null,
                'clarity_id' => $m['clarity_id'] ?? null,
                'tiktok_pixel_id' => $m['tiktok_pixel_id'] ?? null,
            ],
        ]);
    }
}
