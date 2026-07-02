<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function whatsappAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // has settings.manage

    return $user;
}

it('blocks the WhatsApp settings for users without settings.manage', function () {
    $user = User::factory()->create();
    $user->assignRole('editor');

    actingAs($user)->get('/settings/whatsapp')->assertForbidden();
    actingAs($user)->post('/settings/whatsapp', [])->assertForbidden();
});

it('saves the WhatsApp number and per-button toggles', function () {
    actingAs(whatsappAdmin())
        ->post('/settings/whatsapp', [
            'whatsapp' => '8801748870651',
            'floating_enabled' => '1',
            'inquiry_enabled' => '1',
            // footer_enabled omitted → off
        ])
        ->assertRedirect(route('whatsapp-settings.edit'));

    $settings = app(SettingsService::class);
    expect($settings->get('branding', 'whatsapp'))->toBe('8801748870651')
        ->and($settings->get('branding', 'whatsapp_floating_enabled'))->toBe('1')
        ->and($settings->get('branding', 'whatsapp_inquiry_enabled'))->toBe('1')
        ->and($settings->get('branding', 'whatsapp_footer_enabled'))->toBe('0');
});

it('exposes the number and button flags via the public api (defaults on)', function () {
    app(SettingsService::class)->set('branding', 'whatsapp', '8801748870651');

    $this->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('data.whatsapp', '8801748870651')
        ->assertJsonPath('data.whatsapp_buttons.floating', true)
        ->assertJsonPath('data.whatsapp_buttons.inquiry', true)
        ->assertJsonPath('data.whatsapp_buttons.footer', true);
});

it('reflects a hidden button in the public api', function () {
    actingAs(whatsappAdmin())
        ->post('/settings/whatsapp', [
            'whatsapp' => '8801748870651',
            'floating_enabled' => '1',
            'inquiry_enabled' => '1',
            // footer off
        ])
        ->assertRedirect(route('whatsapp-settings.edit'));

    $this->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('data.whatsapp_buttons.footer', false)
        ->assertJsonPath('data.whatsapp_buttons.floating', true);
});

it('rejects a non-numeric WhatsApp number', function () {
    actingAs(whatsappAdmin())
        ->post('/settings/whatsapp', ['whatsapp' => '+88 017-bad'])
        ->assertSessionHasErrors('whatsapp');
});
