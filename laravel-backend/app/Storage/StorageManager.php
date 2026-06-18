<?php

declare(strict_types=1);

namespace App\Storage;

use App\Services\Settings\SettingsService;
use App\Storage\Contracts\StorageRepository;
use App\Storage\Drivers\CloudflareR2Storage;
use App\Storage\Drivers\ServerDiskStorage;
use RuntimeException;

/**
 * Resolves the active StorageRepository from settings (default: server disk).
 * Selecting R2 without configured credentials fails loudly rather than
 * silently falling back to local.
 */
final class StorageManager
{
    public function __construct(private readonly SettingsService $settings) {}

    public function driver(): StorageRepository
    {
        $driver = (string) $this->settings->get('storage', 'driver', 'server');

        return match ($driver) {
            'r2' => $this->makeR2(),
            default => new ServerDiskStorage,
        };
    }

    private function makeR2(): CloudflareR2Storage
    {
        $config = config('filesystems.disks.r2', []);

        if (blank($config['key'] ?? null) || blank($config['secret'] ?? null) || blank($config['bucket'] ?? null)) {
            throw new RuntimeException('R2 storage is selected but its credentials are not configured.');
        }

        return new CloudflareR2Storage;
    }
}
