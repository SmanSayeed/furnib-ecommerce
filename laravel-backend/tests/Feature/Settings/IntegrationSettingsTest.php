<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\PermissionRoleSeeder;
use Inertia\Testing\AssertableInertia;

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

it('saves live SSLCommerz credentials encrypted + masked, without wiping sandbox', function () {
    // Pre-existing sandbox credentials.
    $settings = app(SettingsService::class);
    $settings->set('sslcommerz', 'sandbox_store_id', 'testbox', false);
    $settings->set('sslcommerz', 'sandbox_store_passwd', 'sbox-pass', true);

    actingAs(settingsAdmin())->post('/settings/sslcommerz', [
        'sandbox' => false,
        'live_store_id' => 'furnib_live',
        'live_store_passwd' => 'ssl-secret-pass',
    ])->assertRedirect();

    $row = Setting::query()->where('group', 'sslcommerz')->where('key', 'live_store_passwd')->firstOrFail();
    expect($row->is_secret)->toBeTrue()
        ->and($row->value)->not->toBe('ssl-secret-pass');

    expect($settings->get('sslcommerz', 'live_store_id'))->toBe('furnib_live')
        ->and($settings->get('sslcommerz', 'sandbox'))->toBeFalse()
        ->and($settings->get('sslcommerz', 'live_store_passwd'))->toBe('ssl-secret-pass')
        // Sandbox credentials are untouched by saving live.
        ->and($settings->get('sslcommerz', 'sandbox_store_id'))->toBe('testbox')
        ->and($settings->get('sslcommerz', 'sandbox_store_passwd'))->toBe('sbox-pass')
        ->and($settings->toArray('sslcommerz'))->toMatchArray(['live_store_passwd' => null]);
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

it('renders the integrations page with masked secret-set flags', function () {
    $settings = app(SettingsService::class);
    $settings->set('sslcommerz', 'live_store_id', 'furnib_live', false);
    $settings->set('sslcommerz', 'live_store_passwd', 'ssl-secret', true);
    $settings->set('sslcommerz', 'sandbox', false);
    $settings->set('steadfast', 'api_key', 'sf-key', true);

    actingAs(settingsAdmin())
        ->get('/settings/integrations')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/integrations')
            ->where('sslcommerz.live_store_id', 'furnib_live')
            ->where('sslcommerz.sandbox', false)
            ->where('sslcommerz.live_store_passwd_set', true)
            ->where('sslcommerz.sandbox_store_passwd_set', false)
            ->where('steadfast.api_key_set', true)
            ->where('steadfast.secret_key_set', false)
            // Secrets must never reach the client.
            ->missing('sslcommerz.live_store_passwd')
            ->missing('steadfast.api_key'));
});

it('forbids users without settings.manage from viewing the integrations page', function () {
    $user = User::factory()->create();
    $user->assignRole('sub-admin');

    actingAs($user)->get('/settings/integrations')->assertForbidden();
});

it('forbids users without settings.manage from saving gateway credentials', function () {
    $user = User::factory()->create();
    $user->assignRole('sub-admin');

    actingAs($user)->post('/settings/sslcommerz', ['store_id' => 'x', 'sandbox' => true])
        ->assertForbidden();
    actingAs($user)->post('/settings/steadfast', ['api_key' => 'x'])
        ->assertForbidden();
});
