<?php

declare(strict_types=1);

use App\Services\Catalog\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

it('converts an uploaded image to webp and stores it via the storage repository', function () {
    Storage::fake('public');

    $path = app(ImageOptimizer::class)
        ->optimizeAndStore(UploadedFile::fake()->image('chair.jpg', 800, 600), 'products');

    expect($path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($path);
});

it('downscales an image wider than the maximum width', function () {
    Storage::fake('public');

    $path = app(ImageOptimizer::class)
        ->optimizeAndStore(UploadedFile::fake()->image('big.jpg', 3000, 2000), 'products');

    $stored = (new ImageManager(new Driver))->decodePath(Storage::disk('public')->path($path));

    expect($stored->width())->toBeLessThanOrEqual(1600);
});
