<?php

declare(strict_types=1);

use App\Services\Settings\SettingsService;
use App\Storage\Contracts\StorageRepository;
use App\Storage\Drivers\CloudflareR2Storage;
use App\Storage\Drivers\ServerDiskStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('defaults to the server disk driver', function () {
    expect(app(StorageRepository::class))->toBeInstanceOf(ServerDiskStorage::class);
});

it('switches to the r2 driver via settings when credentials are present', function () {
    config([
        'filesystems.disks.r2.key' => 'key',
        'filesystems.disks.r2.secret' => 'secret',
        'filesystems.disks.r2.bucket' => 'bucket',
    ]);
    app(SettingsService::class)->set('storage', 'driver', 'r2');

    expect(app(StorageRepository::class))->toBeInstanceOf(CloudflareR2Storage::class);
});

it('throws when r2 is selected but credentials are missing', function () {
    config([
        'filesystems.disks.r2.key' => null,
        'filesystems.disks.r2.secret' => null,
        'filesystems.disks.r2.bucket' => null,
    ]);
    app(SettingsService::class)->set('storage', 'driver', 'r2');

    app(StorageRepository::class);
})->throws(RuntimeException::class);

it('stores, resolves a url for, and deletes a file via the server driver', function () {
    Storage::fake('public');
    $storage = new ServerDiskStorage;

    $path = $storage->store(UploadedFile::fake()->create('chair.jpg', 100), 'products');

    expect($storage->exists($path))->toBeTrue()
        ->and($storage->url($path))->toContain($path)
        ->and($storage->delete($path))->toBeTrue()
        ->and($storage->exists($path))->toBeFalse();
});
