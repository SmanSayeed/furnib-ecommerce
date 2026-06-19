<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SslcommerzSettingsRequest;
use App\Http\Requests\Settings\SteadfastSettingsRequest;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Admin editor for payment/courier gateway credentials. Secrets are stored
 * encrypted and write-only — left blank, the stored value is kept.
 */
class IntegrationSettingController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function updateSslcommerz(SslcommerzSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->settings->set('sslcommerz', 'store_id', $validated['store_id']);
        $this->settings->set('sslcommerz', 'sandbox', (bool) $validated['sandbox']);

        if (filled($validated['store_passwd'] ?? null)) {
            $this->settings->set('sslcommerz', 'store_passwd', $validated['store_passwd'], isSecret: true);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('SSLCommerz settings saved.')]);

        return back();
    }

    public function updateSteadfast(SteadfastSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        foreach (['api_key', 'secret_key'] as $key) {
            if (filled($validated[$key] ?? null)) {
                $this->settings->set('steadfast', $key, $validated[$key], isSecret: true);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Courier settings saved.')]);

        return back();
    }
}
