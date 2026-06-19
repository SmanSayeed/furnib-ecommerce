<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function settingsAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // settings.manage

    return $user;
}

it('saves SSLCommerz credentials with the password encrypted + masked', function () {
    actingAs(settingsAdmin())->post('/settings/sslcommerz', [
        'store_id' => 'furnib_live',
        'store_passwd' => 'ssl-secret-pass',
        'sandbox' => false,
    ])->assertRedirect();

    $row = Setting::query()->where('group', 'sslcommerz')->where('key', 'store_passwd')->firstOrFail();
    expect($row->is_secret)->toBeTrue()
        ->and($row->value)->not->toBe('ssl-secret-pass');

    $settings = app(SettingsService::class);
    expect($settings->get('sslcommerz', 'store_id'))->toBe('furnib_live')
        ->and($settings->get('sslcommerz', 'sandbox'))->toBeFalse()
        ->and($settings->get('sslcommerz', 'store_passwd'))->toBe('ssl-secret-pass')
        ->and($settings->toArray('sslcommerz'))->toMatchArray(['store_passwd' => null]);
});

it('saves SteadFast credentials with both keys encrypted', function () {
    actingAs(settingsAdmin())->post('/settings/steadfast', [
        'api_key' => 'sf-api-key',
        'secret_key' => 'sf-secret-key',
    ])->assertRedirect();

    $settings = app(SettingsService::class);
    expect($settings->get('steadfast', 'api_key'))->toBe('sf-api-key')
        ->and($settings->get('steadfast', 'secret_key'))->toBe('sf-secret-key')
        ->and($settings->toArray('steadfast'))->toMatchArray(['api_key' => null, 'secret_key' => null]);

    $row = Setting::query()->where('group', 'steadfast')->where('key', 'secret_key')->firstOrFail();
    expect($row->is_secret)->toBeTrue();
});

it('forbids users without settings.manage from saving gateway credentials', function () {
    $user = User::factory()->create();
    $user->assignRole('sub-admin');

    actingAs($user)->post('/settings/sslcommerz', ['store_id' => 'x', 'sandbox' => true])
        ->assertForbidden();
    actingAs($user)->post('/settings/steadfast', ['api_key' => 'x'])
        ->assertForbidden();
});
