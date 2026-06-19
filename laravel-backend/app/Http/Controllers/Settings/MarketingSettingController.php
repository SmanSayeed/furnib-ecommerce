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
    private const PUBLIC_KEYS = ['gtm_id', 'ga4_id', 'fb_pixel_id', 'clarity_id'];

    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): Response
    {
        $marketing = $this->settings->toArray(self::GROUP); // masks fb_capi_token
        $marketing['fb_capi_token_set'] = filled($this->settings->get(self::GROUP, 'fb_capi_token'));

        return Inertia::render('settings/marketing', [
            'marketing' => $marketing,
        ]);
    }

    public function update(MarketingSettingsUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        foreach (self::PUBLIC_KEYS as $key) {
            $this->settings->set(self::GROUP, $key, $validated[$key] ?? null);
        }

        // CAPI token is write-only: only overwrite when a new one is supplied.
        if (filled($validated['fb_capi_token'] ?? null)) {
            $this->settings->set(self::GROUP, 'fb_capi_token', $validated['fb_capi_token'], isSecret: true);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marketing settings saved.')]);

        return back();
    }
}
