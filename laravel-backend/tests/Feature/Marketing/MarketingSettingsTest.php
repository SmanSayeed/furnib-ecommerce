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

function marketer(): User
{
    $user = User::factory()->create();
    $user->assignRole('marketer'); // marketing.manage

    return $user;
}

it('exposes only the public analytics IDs on the storefront endpoint', function () {
    $settings = app(SettingsService::class);
    $settings->set('marketing', 'gtm_id', 'GTM-XXXX');
    $settings->set('marketing', 'ga4_id', 'G-YYYY');
    $settings->set('marketing', 'fb_pixel_id', '123456');
    $settings->set('marketing', 'clarity_id', 'abcd');

    $this->getJson('/api/v1/marketing')
        ->assertOk()
        ->assertJsonPath('data.gtm_id', 'GTM-XXXX')
        ->assertJsonPath('data.ga4_id', 'G-YYYY')
        ->assertJsonPath('data.fb_pixel_id', '123456')
        ->assertJsonPath('data.clarity_id', 'abcd');
});

it('never exposes the Meta CAPI token in the public response', function () {
    $settings = app(SettingsService::class);
    $settings->set('marketing', 'gtm_id', 'GTM-XXXX');
    $settings->set('marketing', 'fb_capi_token', 'EAAB-super-secret-token', isSecret: true);

    $response = $this->getJson('/api/v1/marketing')->assertOk();

    expect($response->getContent())->not->toContain('EAAB-super-secret-token')
        ->and($response->getContent())->not->toContain('fb_capi_token')
        ->and($response->json('data'))->not->toHaveKey('fb_capi_token');
});

it('lets a marketer save settings with the CAPI token encrypted + masked', function () {
    actingAs(marketer())->post('/settings/marketing', [
        'gtm_id' => 'GTM-1',
        'ga4_id' => 'G-1',
        'fb_pixel_id' => '999',
        'clarity_id' => 'cc',
        'fb_capi_token' => 'EAAB-secret',
    ])->assertRedirect();

    $row = Setting::query()->where('group', 'marketing')->where('key', 'fb_capi_token')->firstOrFail();
    expect($row->is_secret)->toBeTrue()
        ->and($row->value)->not->toBe('EAAB-secret');

    $settings = app(SettingsService::class);
    expect($settings->get('marketing', 'fb_capi_token'))->toBe('EAAB-secret')
        ->and($settings->toArray('marketing'))->toMatchArray(['fb_capi_token' => null]);
});

it('forbids users without marketing.manage from editing', function () {
    $user = User::factory()->create();
    $user->assignRole('sub-admin'); // no marketing.manage

    actingAs($user)->post('/settings/marketing', ['gtm_id' => 'x'])
        ->assertForbidden();
});
