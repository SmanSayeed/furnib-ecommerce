<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\PermissionRoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
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
        ])
        ->assertRedirect(route('site-settings.edit'));

    expect(Setting::where('group', 'branding')->where('key', 'site_name')->value('value'))
        ->toBe('Furnib BD');
    expect(Setting::where('group', 'branding')->where('key', 'tagline')->value('value'))
        ->toBe('Comfort first');
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

it('uploads the invoice logo and stores its path', function () {
    Storage::fake('public');

    actingAs(adminUser())
        ->post('/settings/site', [
            'site_name' => 'Furnib BD',
            'logo_invoice' => UploadedFile::fake()->image('invoice.png', 200, 60),
        ])
        ->assertRedirect(route('site-settings.edit'));

    $invoice = Setting::where('group', 'branding')->where('key', 'logo_invoice')->value('value');

    expect($invoice)->not->toBeNull();
    Storage::disk('public')->assertExists($invoice);
});

it('exposes the footer logo url via the public api', function () {
    app(SettingsService::class)->set('branding', 'logo_footer', 'branding/footer.png');

    $this->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('data.logo_footer', fn ($url) => is_string($url) && str_contains($url, 'branding/footer.png'));
});

it('rejects an svg logo upload', function () {
    actingAs(adminUser())
        ->post('/settings/site', [
            'site_name' => 'Furnib BD',
            'logo_light' => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('logo_light');
});

it('exposes public branding via the api', function () {
    app(SettingsService::class)->set('branding', 'site_name', 'Furnib Public');

    $this->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('data.site_name', 'Furnib Public');
});
