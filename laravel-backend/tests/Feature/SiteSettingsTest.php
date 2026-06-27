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

it('uploads footer and invoice logos and stores their paths', function () {
    Storage::fake('public');

    actingAs(adminUser())
        ->post('/settings/site', [
            'site_name' => 'Furnib BD',
            'logo_footer' => UploadedFile::fake()->image('footer.png', 200, 60),
            'logo_invoice' => UploadedFile::fake()->image('invoice.png', 200, 60),
        ])
        ->assertRedirect(route('site-settings.edit'));

    $footer = Setting::where('group', 'branding')->where('key', 'logo_footer')->value('value');
    $invoice = Setting::where('group', 'branding')->where('key', 'logo_invoice')->value('value');

    expect($footer)->not->toBeNull();
    expect($invoice)->not->toBeNull();
    Storage::disk('public')->assertExists($footer);
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

it('rejects a non-numeric whatsapp number', function () {
    actingAs(adminUser())
        ->post('/settings/site', [
            'site_name' => 'Furnib BD',
            'whatsapp' => '+88 017-bad',
        ])
        ->assertSessionHasErrors('whatsapp');
});

it('exposes public branding via the api', function () {
    app(SettingsService::class)->set('branding', 'site_name', 'Furnib Public');

    $this->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('data.site_name', 'Furnib Public');
});

it('saves footer social links and quick links', function () {
    actingAs(adminUser())
        ->post('/settings/site', [
            'site_name' => 'Furnib BD',
            'social_facebook' => 'https://facebook.com/furnib',
            'social_instagram' => 'https://instagram.com/furnib',
            'about_links' => [
                ['label' => 'Privacy Policy', 'url' => '/privacy'],
                ['label' => 'Blog', 'url' => 'https://furnib.com/blog'],
            ],
        ])
        ->assertRedirect(route('site-settings.edit'));

    $settings = app(SettingsService::class);
    expect($settings->get('branding', 'social_facebook'))->toBe('https://facebook.com/furnib')
        ->and($settings->get('branding', 'about_links'))->toBe([
            ['label' => 'Privacy Policy', 'url' => '/privacy'],
            ['label' => 'Blog', 'url' => 'https://furnib.com/blog'],
        ]);
});

it('exposes footer socials and links via the public api', function () {
    $settings = app(SettingsService::class);
    $settings->set('branding', 'social_facebook', 'https://facebook.com/furnib');
    $settings->set('branding', 'about_links', [['label' => 'Privacy', 'url' => '/privacy']]);

    $this->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('data.socials.facebook', 'https://facebook.com/furnib')
        ->assertJsonPath('data.footer_links.0.label', 'Privacy')
        ->assertJsonPath('data.footer_links.0.url', '/privacy');
});

it('rejects a javascript: url in a footer link (xss guard)', function () {
    actingAs(adminUser())
        ->post('/settings/site', [
            'site_name' => 'Furnib BD',
            'about_links' => [
                ['label' => 'Evil', 'url' => 'javascript:alert(1)'],
            ],
        ])
        ->assertSessionHasErrors('about_links.0.url');
});

it('rejects a non-http social url', function () {
    actingAs(adminUser())
        ->post('/settings/site', [
            'site_name' => 'Furnib BD',
            'social_facebook' => 'javascript:alert(1)',
        ])
        ->assertSessionHasErrors('social_facebook');
});
