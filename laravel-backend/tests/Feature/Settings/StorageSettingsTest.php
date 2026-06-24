<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Storage\Contracts\StorageRepository;
use App\Storage\Drivers\CloudflareR2Storage;
use App\Storage\Drivers\ServerDiskStorage;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function storageManager(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo('settings.manage');

    return $user;
}

it('saves the driver + R2 connection with the secret keys encrypted and masked', function () {
    actingAs(storageManager())->post('/settings/storage', [
        'driver' => 'r2',
        'r2_endpoint' => 'https://acct.r2.cloudflarestorage.com',
        'r2_bucket' => 'furnib-ecommerce',
        'r2_url' => 'https://pub-xxxx.r2.dev',
        'r2_region' => 'auto',
        'r2_access_key' => 'AKIA-R2-KEY',
        'r2_secret_key' => 'super-secret-r2',
    ])->assertRedirect();

    $secret = Setting::query()->where('group', 'storage')->where('key', 'r2_secret_key')->firstOrFail();
    expect($secret->is_secret)->toBeTrue()
        ->and($secret->value)->not->toBe('super-secret-r2');

    $settings = app(SettingsService::class);
    expect($settings->get('storage', 'driver'))->toBe('r2')
        ->and($settings->get('storage', 'r2_secret_key'))->toBe('super-secret-r2')
        // Secrets are masked when read for the client.
        ->and($settings->toArray('storage'))->toMatchArray(['r2_secret_key' => null, 'r2_access_key' => null]);
});

it('refuses to enable R2 without complete credentials', function () {
    // Ensure there is no env-backed fallback for this assertion.
    config(['filesystems.disks.r2' => [
        'key' => null, 'secret' => null, 'bucket' => null, 'endpoint' => null, 'url' => null,
    ]]);

    actingAs(storageManager())->post('/settings/storage', [
        'driver' => 'r2',
        // no bucket / endpoint / keys, and none stored or in env
    ])->assertSessionHasErrors(['r2_bucket', 'r2_endpoint', 'r2_access_key', 'r2_secret_key']);
});

it('allows switching back to the server disk freely', function () {
    actingAs(storageManager())->post('/settings/storage', ['driver' => 'server'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(app(SettingsService::class)->get('storage', 'driver'))->toBe('server');
});

it('resolves the active StorageRepository from the saved driver', function () {
    $settings = app(SettingsService::class);

    // Default → server disk.
    expect(app(StorageRepository::class))->toBeInstanceOf(ServerDiskStorage::class);

    // Configure + select R2 → R2 driver.
    $settings->set('storage', 'r2_access_key', 'k', isSecret: true);
    $settings->set('storage', 'r2_secret_key', 's', isSecret: true);
    $settings->set('storage', 'r2_bucket', 'b');
    $settings->set('storage', 'r2_endpoint', 'https://acct.r2.cloudflarestorage.com');
    $settings->set('storage', 'driver', 'r2');

    expect(app(StorageRepository::class))->toBeInstanceOf(CloudflareR2Storage::class);
});

it('forbids users without settings.manage from editing', function () {
    $user = User::factory()->create();
    $user->assignRole('marketer'); // marketing.manage, not settings.manage

    actingAs($user)->post('/settings/storage', ['driver' => 'server'])
        ->assertForbidden();
});
