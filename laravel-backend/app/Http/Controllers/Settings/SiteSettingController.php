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

class SiteSettingController extends Controller
{
    private const GROUP = 'branding';

    /** Text fields and their defaults (default pulled from config when unset). */
    private const TEXT_KEYS = [
        'site_name',
        'tagline',
        'whatsapp',
        'contact_phone',
        'contact_email',
        'contact_address',
    ];

    /** Social platforms shown under "Follow us" — each has a url + enabled flag. */
    private const SOCIAL_PLATFORMS = [
        'facebook',
        'instagram',
        'youtube',
        'linkedin',
        'x',
        'pinterest',
        'tiktok',
    ];

    /** Uploadable file fields. */
    private const FILE_KEYS = ['logo_light', 'logo_dark', 'logo_footer', 'logo_invoice', 'favicon', 'banner_1', 'banner_2'];

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

        // "Follow us" — each platform stores its url and a visibility flag.
        foreach (self::SOCIAL_PLATFORMS as $platform) {
            $this->settings->set(self::GROUP, "social_{$platform}", $request->string("social_{$platform}")->toString());
            $this->settings->set(self::GROUP, "social_{$platform}_enabled", $request->boolean("social_{$platform}_enabled") ? '1' : '0');
        }

        // Footer quick links (label + url array). Only touched when submitted,
        // so unrelated saves don't wipe them. Re-keyed to a clean list.
        if ($request->exists('about_links')) {
            /** @var array<int, array{label:string, url:string}> $links */
            $links = $request->validated('about_links') ?? [];
            $this->settings->set(self::GROUP, 'about_links', array_values($links));
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

        // Social url + enabled flag (defaults to enabled when never toggled).
        foreach (self::SOCIAL_PLATFORMS as $platform) {
            $data["social_{$platform}"] = (string) ($this->settings->get(self::GROUP, "social_{$platform}") ?? '');
            $data["social_{$platform}_enabled"] = $this->settings->get(self::GROUP, "social_{$platform}_enabled") !== '0';
        }

        foreach (self::FILE_KEYS as $key) {
            $path = $this->settings->get(self::GROUP, $key);
            $data[$key.'_url'] = is_string($path) && $path !== '' ? $this->storage->url($path) : null;
        }

        $links = $this->settings->get(self::GROUP, 'about_links');
        $data['about_links'] = is_array($links) ? array_values($links) : [];

        return $data;
    }
}
