<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Storage\Contracts\StorageRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Converts an uploaded image to an optimized WebP (downscaling oversized
 * images) and stores it through the active storage driver. Returns the path.
 */
final class ImageOptimizer
{
    public function __construct(
        private readonly StorageRepository $storage,
        private readonly int $maxWidth = 1600,
        private readonly int $quality = 82,
    ) {}

    public function optimizeAndStore(UploadedFile $file, string $directory = 'products'): string
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->decodePath($file->getRealPath());

        if ($image->width() > $this->maxWidth) {
            $image->scaleDown(width: $this->maxWidth);
        }

        $contents = (string) $image->encode(new WebpEncoder(quality: $this->quality));
        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.webp';

        return $this->storage->put($path, $contents);
    }
}
