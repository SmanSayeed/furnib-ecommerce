<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionRoleSeeder::class);
});

function adminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // has settings.manage

    return $user;
}

it('blocks users without settings.manage', function () {
    $user = User::factory()->create();
    $user->assignRole('editor'); // no settings.manage

    actingAs($user)
        ->post('/settings/site', ['site_name' => 'Hacked'])
        ->assertForbidden();
});

it('saves branding text settings', function () {
    actingAs(adminUser())
        ->post('/settings/site', [
            'site_name' => 'Furnib BD',
            'tagline' => 'Comfort first',
            'whatsapp' => '8801711112222',
            'contact_email' => 'hi@furnib.com',
        ])
        ->assertRedirect(route('site-settings.edit'));

    expect(Setting::where('group', 'branding')->where('key', 'site_name')->value('value'))
        ->toBe('Furnib BD');
    expect(Setting::where('group', 'branding')->where('key', 'whatsapp')->value('value'))
        ->toBe('8801711112222');
});

it('uploads a logo and stores its path', function () {
    Storage::fake('public');

    actingAs(adminUser())
        ->post('/settings/site', [
            'site_name' => 'Furnib BD',
            'logo_light' => UploadedFile::fake()->image('logo.png', 200, 60),
        ])
        ->assertRedirect(route('site-settings.edit'));

    $path = Setting::where('group', 'branding')->where('key', 'logo_light')->value('value');

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('rejects an svg logo upload', function () {
    actingAs(adminUser())
        ->post('/settings/site', [
            'site_name' => 'Furnib BD',
            'logo_light' => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('logo_light');
});

it('rejects a non-numeric whatsapp number', function () {
    actingAs(adminUser())
        ->post('/settings/site', [
            'site_name' => 'Furnib BD',
            'whatsapp' => '+88 017-bad',
        ])
        ->assertSessionHasErrors('whatsapp');
});

it('exposes public branding via the api', function () {
    app(\App\Services\Settings\SettingsService::class)->set('branding', 'site_name', 'Furnib Public');

    $this->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('data.site_name', 'Furnib Public');
});
