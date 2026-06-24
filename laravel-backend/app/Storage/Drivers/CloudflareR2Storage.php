<?php

declare(strict_types=1);

namespace App\Storage\Drivers;

use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Storage;

/**
 * Cloudflare R2 (S3-compatible) storage. The disk config is merged from admin
 * settings (group 'storage') on top of the env-backed `filesystems.disks.r2`
 * defaults — so the owner can manage R2 entirely from the admin panel, with the
 * env values acting as a fallback.
 */
final class CloudflareR2Storage extends DiskStorage
{
    /** Admin setting key => `filesystems.disks.r2` config key. */
    private const MAP = [
        'r2_access_key' => 'key',
        'r2_secret_key' => 'secret',
        'r2_region' => 'region',
        'r2_bucket' => 'bucket',
        'r2_url' => 'url',
        'r2_endpoint' => 'endpoint',
    ];

    public function __construct(SettingsService $settings)
    {
        $config = (array) config('filesystems.disks.r2', []);

        foreach (self::MAP as $settingKey => $configKey) {
            $value = $settings->get('storage', $settingKey);
            if (filled($value)) {
                $config[$configKey] = (string) $value;
            }
        }

        config(['filesystems.disks.r2' => $config]);
        Storage::forgetDisk('r2'); // rebuild the disk with the merged config
    }

    protected function disk(): string
    {
        return 'r2';
    }

    /**
     * The effective (settings-merged) R2 disk config.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return (array) config('filesystems.disks.r2', []);
    }
}
