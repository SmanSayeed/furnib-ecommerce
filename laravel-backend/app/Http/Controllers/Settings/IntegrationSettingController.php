<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SslcommerzSettingsRequest;
use App\Http\Requests\Settings\SteadfastSettingsRequest;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin editor for payment/courier gateway credentials. Secrets are stored
 * encrypted and write-only — left blank, the stored value is kept. The edit
 * page only ever exposes non-secret fields plus a boolean "is it configured"
 * flag per secret; the secret values themselves never reach the browser.
 */
class IntegrationSettingController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): Response
    {
        return Inertia::render('settings/integrations', [
            'sslcommerz' => [
                'store_id' => (string) ($this->settings->get('sslcommerz', 'store_id') ?? ''),
                'sandbox' => (bool) $this->settings->get('sslcommerz', 'sandbox', true),
                'store_passwd_set' => filled($this->settings->get('sslcommerz', 'store_passwd')),
            ],
            'steadfast' => [
                'api_key_set' => filled($this->settings->get('steadfast', 'api_key')),
                'secret_key_set' => filled($this->settings->get('steadfast', 'secret_key')),
            ],
        ]);
    }

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
