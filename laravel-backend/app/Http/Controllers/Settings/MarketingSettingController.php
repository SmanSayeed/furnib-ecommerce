<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MarketingSettingsUpdateRequest;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin editor for marketing/analytics settings. The Meta CAPI token is stored
 * encrypted and never returned to the client (masked in the edit payload).
 */
class MarketingSettingController extends Controller
{
    private const GROUP = 'marketing';

    /** Public (client-safe) ID fields. */
    private const PUBLIC_KEYS = ['gtm_id', 'ga4_id', 'fb_pixel_id', 'clarity_id', 'tiktok_pixel_id'];

    /** Server-side-only, non-secret fields (not exposed to the storefront). */
    private const SERVER_KEYS = ['fb_test_event_code', 'tiktok_test_event_code'];

    /** Write-only server-side secrets (never returned to the client). */
    private const SECRET_KEYS = ['fb_capi_token', 'tiktok_access_token', 'ga4_api_secret'];

    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): Response
    {
        $marketing = $this->settings->toArray(self::GROUP); // masks secrets

        // Expose only WHETHER each secret is configured — never the value.
        foreach (self::SECRET_KEYS as $key) {
            $marketing[$key.'_set'] = filled($this->settings->get(self::GROUP, $key));
        }

        return Inertia::render('settings/marketing', [
            'marketing' => $marketing,
        ]);
    }

    public function update(MarketingSettingsUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        foreach ([...self::PUBLIC_KEYS, ...self::SERVER_KEYS] as $key) {
            $this->settings->set(self::GROUP, $key, $validated[$key] ?? null);
        }

        // Secrets are write-only: only overwrite when a new value is supplied.
        foreach (self::SECRET_KEYS as $key) {
            if (filled($validated[$key] ?? null)) {
                $this->settings->set(self::GROUP, $key, $validated[$key], isSecret: true);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marketing settings saved.')]);

        return back();
    }
}
