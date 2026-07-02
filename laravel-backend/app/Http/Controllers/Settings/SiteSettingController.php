<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SiteSettingsUpdateRequest;
use App\Services\Settings\SettingsService;
use App\Storage\Contracts\StorageRepository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Core store identity + logos. Footer-specific settings (social links and the
 * footer contact/quick-links) live under their own "Footer settings" pages
 * (FooterSocialController / FooterDetailController) but share the `branding`
 * group, so the public settings API shape is unchanged.
 */
class SiteSettingController extends Controller
{
    private const GROUP = 'branding';

    /** Text fields owned by this page. */
    private const TEXT_KEYS = [
        'site_name',
        'tagline',
    ];

    /** Uploadable file fields. */
    private const FILE_KEYS = [
        'logo_light', 'logo_dark', 'logo_invoice', 'favicon',
        // Legacy single-image banner slots (kept for backward compatibility).
        'banner_1', 'banner_2',
        // Responsive banner slots (desktop + mobile per slot).
        'banner_1_desktop', 'banner_1_mobile',
        'banner_2_desktop', 'banner_2_mobile',
    ];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly StorageRepository $storage,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('settings/site', [
            'branding' => $this->payload(),
        ]);
    }

    public function update(SiteSettingsUpdateRequest $request): RedirectResponse
    {
        foreach (self::TEXT_KEYS as $key) {
            $this->settings->set(self::GROUP, $key, $request->string($key)->toString());
        }

        foreach (self::FILE_KEYS as $key) {
            if ($request->hasFile($key)) {
                $old = $this->settings->get(self::GROUP, $key);
                $path = $this->storage->store($request->file($key), self::GROUP);
                $this->settings->set(self::GROUP, $key, $path);

                if (is_string($old) && $old !== '' && $old !== $path) {
                    $this->storage->delete($old);
                }
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Site settings updated.')]);

        return to_route('site-settings.edit');
    }

    /**
     * Current branding values + resolved preview URLs.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $data = [];
        foreach (self::TEXT_KEYS as $key) {
            $data[$key] = (string) ($this->settings->get(self::GROUP, $key) ?? '');
        }

        foreach (self::FILE_KEYS as $key) {
            $path = $this->settings->get(self::GROUP, $key);
            $data[$key.'_url'] = is_string($path) && $path !== '' ? $this->storage->url($path) : null;
        }

        return $data;
    }
}
