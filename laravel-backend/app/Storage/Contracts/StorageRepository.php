<?php

declare(strict_types=1);

namespace App\Storage\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * Storage abstraction used by every upload feature. Calling code depends only
 * on this interface; the concrete driver (server disk / Cloudflare R2) is
 * resolved from settings at runtime.
 */
interface StorageRepository
{
    public function store(UploadedFile|string $file, string $directory = ''): string;

    /**
     * Write raw contents at an explicit path; returns the stored path.
     */
    public function put(string $path, string $contents): string;

    public function url(string $path): string;

    public function delete(string $path): bool;

    public function exists(string $path): bool;
}
