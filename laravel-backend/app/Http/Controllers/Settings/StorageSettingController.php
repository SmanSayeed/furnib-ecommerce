<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StorageSettingsUpdateRequest;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin editor for media storage. Lets the owner pick the active driver
 * (server disk / Cloudflare R2) and manage the R2 connection. The R2 access
 * key + secret are stored encrypted and are write-only (masked on read).
 */
class StorageSettingController extends Controller
{
    private const GROUP = 'storage';

    /** Non-secret fields. */
    private const PUBLIC_KEYS = ['driver', 'r2_endpoint', 'r2_bucket', 'r2_url', 'r2_region'];

    /** Encrypted, write-only fields. */
    private const SECRET_KEYS = ['r2_access_key', 'r2_secret_key'];

    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): Response
    {
        $storage = $this->settings->toArray(self::GROUP); // masks secrets

        $storage['driver'] = $storage['driver'] ?? 'server';

        // Show effective non-secret values, falling back to the env-backed disk.
        $storage['r2_endpoint'] = $storage['r2_endpoint'] ?? config('filesystems.disks.r2.endpoint');
        $storage['r2_bucket'] = $storage['r2_bucket'] ?? config('filesystems.disks.r2.bucket');
        $storage['r2_url'] = $storage['r2_url'] ?? config('filesystems.disks.r2.url');
        $storage['r2_region'] = $storage['r2_region'] ?? config('filesystems.disks.r2.region');

        // Surface whether each secret is configured (in settings OR via env).
        $storage['r2_access_key_set'] = filled($this->settings->get(self::GROUP, 'r2_access_key'))
            || filled(config('filesystems.disks.r2.key'));
        $storage['r2_secret_key_set'] = filled($this->settings->get(self::GROUP, 'r2_secret_key'))
            || filled(config('filesystems.disks.r2.secret'));

        return Inertia::render('settings/storage', [
            'storage' => $storage,
        ]);
    }

    public function update(StorageSettingsUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        foreach (self::PUBLIC_KEYS as $key) {
            $this->settings->set(self::GROUP, $key, $validated[$key] ?? null);
        }

        // Secrets are write-only: only overwrite when a new value is supplied.
        foreach (self::SECRET_KEYS as $key) {
            if (filled($validated[$key] ?? null)) {
                $this->settings->set(self::GROUP, $key, $validated[$key], isSecret: true);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Storage settings saved.')]);

        return back();
    }
}
