<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Enums\OrderNotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SmsSettingsRequest;
use App\Http\Requests\Settings\SslcommerzSettingsRequest;
use App\Http\Requests\Settings\SteadfastSettingsRequest;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
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
                'sandbox' => (bool) $this->settings->get('sslcommerz', 'sandbox', true),
                // Sandbox + live credentials kept side by side. Store ids are shown;
                // passwords are write-only (only a "set" flag reaches the browser).
                // A deploy migration copies any legacy single pair into the active
                // mode, so pre-split installs still show their id in the right slot.
                'sandbox_store_id' => (string) ($this->settings->get('sslcommerz', 'sandbox_store_id') ?? ''),
                'sandbox_store_passwd_set' => filled($this->settings->get('sslcommerz', 'sandbox_store_passwd')),
                'live_store_id' => (string) ($this->settings->get('sslcommerz', 'live_store_id') ?? ''),
                'live_store_passwd_set' => filled($this->settings->get('sslcommerz', 'live_store_passwd')),
            ],
            'steadfast' => [
                'api_key_set' => filled($this->settings->get('steadfast', 'api_key')),
                'secret_key_set' => filled($this->settings->get('steadfast', 'secret_key')),
            ],
            'sms' => [
                'enabled' => (bool) $this->settings->get('sms', 'enabled', false),
                'sender_id' => (string) ($this->settings->get('sms', 'sender_id') ?? ''),
                'api_key_set' => filled($this->settings->get('sms', 'api_key')),
                // Per-event toggle + current (or default) Bangla template.
                'events' => collect(OrderNotificationEvent::cases())->map(fn (OrderNotificationEvent $e): array => [
                    'key' => $e->value,
                    'enabled' => (bool) $this->settings->get('sms', $e->toggleKey(), true),
                    'template' => (string) ($this->settings->get('sms', $e->templateKey()) ?: $e->defaultSmsTemplate()),
                ])->all(),
                // DLR (SMS delivery report) push URLs to paste into the Automas
                // panel. Present only once SMS has been saved (token generated).
                'dlr' => $this->dlrUrls(),
            ],
        ]);
    }

    /**
     * @return array{configured: bool, success_url: string|null, failed_url: string|null}
     */
    private function dlrUrls(): array
    {
        $token = (string) ($this->settings->get('sms', 'dlr_token') ?? '');

        return [
            'configured' => $token !== '',
            'success_url' => $token !== '' ? url("/api/v1/sms/dlr/{$token}/success") : null,
            'failed_url' => $token !== '' ? url("/api/v1/sms/dlr/{$token}/failed") : null,
        ];
    }

    public function updateSslcommerz(SslcommerzSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->settings->set('sslcommerz', 'sandbox', (bool) $validated['sandbox']);

        // Sandbox + live are independent; every credential field is blank-keeps so
        // saving one environment (or just flipping the mode) never wipes the other.
        foreach (['sandbox', 'live'] as $mode) {
            if (filled($validated[$mode.'_store_id'] ?? null)) {
                $this->settings->set('sslcommerz', $mode.'_store_id', $validated[$mode.'_store_id']);
            }

            if (filled($validated[$mode.'_store_passwd'] ?? null)) {
                $this->settings->set('sslcommerz', $mode.'_store_passwd', $validated[$mode.'_store_passwd'], isSecret: true);
            }
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

    public function updateSms(SmsSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->settings->set('sms', 'enabled', (bool) $validated['enabled']);
        $this->settings->set('sms', 'provider', 'automas');
        $this->settings->set('sms', 'sender_id', (string) ($validated['sender_id'] ?? ''));

        if (filled($validated['api_key'] ?? null)) {
            $this->settings->set('sms', 'api_key', $validated['api_key'], isSecret: true);
        }

        // Generate the DLR push token once, so the delivery-report URLs are stable.
        if (blank($this->settings->get('sms', 'dlr_token'))) {
            $this->settings->set('sms', 'dlr_token', Str::random(48), isSecret: true);
        }

        foreach (OrderNotificationEvent::cases() as $event) {
            $this->settings->set('sms', $event->toggleKey(), (bool) $validated[$event->toggleKey()]);
            // Blank template → the code default (Bangla) is used at render time.
            $this->settings->set('sms', $event->templateKey(), (string) ($validated[$event->templateKey()] ?? ''));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('SMS settings saved.')]);

        return back();
    }
}
