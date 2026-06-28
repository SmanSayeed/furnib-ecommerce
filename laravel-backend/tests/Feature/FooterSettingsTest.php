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

function footerAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // has settings.manage

    return $user;
}

// ---- Footer social icons ----

it('blocks footer social settings for users without settings.manage', function () {
    $user = User::factory()->create();
    $user->assignRole('editor');

    actingAs($user)->get('/settings/footer/social')->assertForbidden();
    actingAs($user)->post('/settings/footer/social', [])->assertForbidden();
});

it('saves footer social links', function () {
    actingAs(footerAdmin())
        ->post('/settings/footer/social', [
            'social_facebook' => 'https://facebook.com/furnib',
            'social_instagram' => 'https://instagram.com/furnib',
        ])
        ->assertRedirect(route('footer-social.edit'));

    $settings = app(SettingsService::class);
    expect($settings->get('branding', 'social_facebook'))->toBe('https://facebook.com/furnib')
        ->and($settings->get('branding', 'social_instagram'))->toBe('https://instagram.com/furnib');
});

it('hides a disabled social link from the public api', function () {
    actingAs(footerAdmin())
        ->post('/settings/footer/social', [
            'social_facebook' => 'https://facebook.com/furnib',
            'social_facebook_enabled' => '0',
            'social_instagram' => 'https://instagram.com/furnib',
            'social_instagram_enabled' => '1',
        ])
        ->assertRedirect(route('footer-social.edit'));

    $this->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('data.socials.instagram', 'https://instagram.com/furnib')
        ->assertJsonMissingPath('data.socials.facebook');
});

it('exposes new social platforms (x, tiktok) via the api', function () {
    actingAs(footerAdmin())
        ->post('/settings/footer/social', [
            'social_x' => 'https://x.com/furnib',
            'social_x_enabled' => '1',
            'social_tiktok' => 'https://tiktok.com/@furnib',
            'social_tiktok_enabled' => '1',
        ])
        ->assertRedirect(route('footer-social.edit'));

    $this->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('data.socials.x', 'https://x.com/furnib')
        ->assertJsonPath('data.socials.tiktok', 'https://tiktok.com/@furnib');
});

it('rejects a non-http social url (xss guard)', function () {
    actingAs(footerAdmin())
        ->post('/settings/footer/social', [
            'social_facebook' => 'javascript:alert(1)',
        ])
        ->assertSessionHasErrors('social_facebook');
});

// ---- Footer details (contact + quick links) ----

it('blocks footer details for users without settings.manage', function () {
    $user = User::factory()->create();
    $user->assignRole('editor');

    actingAs($user)->get('/settings/footer/details')->assertForbidden();
    actingAs($user)->post('/settings/footer/details', [])->assertForbidden();
});

it('saves footer contact and quick links', function () {
    actingAs(footerAdmin())
        ->post('/settings/footer/details', [
            'contact_email' => 'hi@furnib.com',
            'contact_phone' => '+880 1712-345678',
            'about_links' => [
                ['label' => 'About us', 'url' => '/p/about-us'],
                ['label' => 'Blog', 'url' => 'https://furnib.com/blog'],
            ],
        ])
        ->assertRedirect(route('footer-details.edit'));

    $settings = app(SettingsService::class);
    expect($settings->get('branding', 'contact_email'))->toBe('hi@furnib.com')
        ->and($settings->get('branding', 'about_links'))->toBe([
            ['label' => 'About us', 'url' => '/p/about-us'],
            ['label' => 'Blog', 'url' => 'https://furnib.com/blog'],
        ]);
});

it('exposes footer links via the public api', function () {
    actingAs(footerAdmin())
        ->post('/settings/footer/details', [
            'about_links' => [['label' => 'Privacy', 'url' => '/p/privacy']],
        ])
        ->assertRedirect(route('footer-details.edit'));

    $this->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('data.footer_links.0.label', 'Privacy')
        ->assertJsonPath('data.footer_links.0.url', '/p/privacy');
});

it('rejects a javascript: url in a footer link (xss guard)', function () {
    actingAs(footerAdmin())
        ->post('/settings/footer/details', [
            'about_links' => [['label' => 'Evil', 'url' => 'javascript:alert(1)']],
        ])
        ->assertSessionHasErrors('about_links.0.url');
});

it('uploads the footer logo and exposes it via the public api', function () {
    Storage::fake('public');

    actingAs(footerAdmin())
        ->post('/settings/footer/details', [
            'logo_footer' => UploadedFile::fake()->image('footer.png', 240, 64),
        ])
        ->assertRedirect(route('footer-details.edit'));

    $path = Setting::where('group', 'branding')->where('key', 'logo_footer')->value('value');
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);

    $this->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('data.logo_footer', fn ($url) => is_string($url) && $url !== '');
});

it('rejects an svg footer logo (xss guard)', function () {
    actingAs(footerAdmin())
        ->post('/settings/footer/details', [
            'logo_footer' => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('logo_footer');
});
