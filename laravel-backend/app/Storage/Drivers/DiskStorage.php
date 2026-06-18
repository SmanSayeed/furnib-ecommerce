<?php

declare(strict_types=1);

namespace App\Storage\Drivers;

use App\Storage\Contracts\StorageRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

abstract class DiskStorage implements StorageRepository
{
    abstract protected function disk(): string;

    public function store(UploadedFile|string $file, string $directory = ''): string
    {
        if ($file instanceof UploadedFile) {
            return (string) $file->store($directory, $this->disk());
        }

        $name = trim($directory.'/'.basename($file), '/');
        Storage::disk($this->disk())->put($name, (string) file_get_contents($file));

        return $name;
    }

    public function put(string $path, string $contents): string
    {
        Storage::disk($this->disk())->put($path, $contents);

        return $path;
    }

    public function url(string $path): string
    {
        return Storage::disk($this->disk())->url($path);
    }

    public function delete(string $path): bool
    {
        return Storage::disk($this->disk())->delete($path);
    }

    public function exists(string $path): bool
    {
        return Storage::disk($this->disk())->exists($path);
    }
}
