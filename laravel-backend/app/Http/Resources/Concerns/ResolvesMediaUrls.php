<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Storage\Contracts\StorageRepository;

trait ResolvesMediaUrls
{
    /**
     * Resolve a stored image path to an absolute URL via the active storage
     * driver (server disk or Cloudflare R2). Already-absolute URLs pass through.
     */
    protected function mediaUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return app(StorageRepository::class)->url($path);
    }
}
