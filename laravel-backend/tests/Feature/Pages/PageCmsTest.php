<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function pageAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // has settings.manage

    return $user;
}

it('blocks users without settings.manage from managing pages', function () {
    $user = User::factory()->create();
    $user->assignRole('editor'); // no settings.manage

    actingAs($user)->get('/admin/pages')->assertForbidden();
    actingAs($user)->post('/admin/pages', ['title' => 'X'])->assertForbidden();
});

it('creates a page and auto-generates a slug from the title', function () {
    actingAs(pageAdmin())
        ->post('/admin/pages', [
            'title' => 'Privacy Policy',
            'body' => '<p>We respect your data.</p>',
            'is_published' => true,
        ])
        ->assertRedirect(route('admin.pages.index'));

    $page = Page::query()->first();
    expect($page)->not->toBeNull()
        ->and($page->slug)->toBe('privacy-policy')
        ->and($page->is_published)->toBeTrue()
        ->and($page->body_html)->toContain('We respect your data.');
});

it('sanitises page html on save (strips script — xss guard)', function () {
    actingAs(pageAdmin())
        ->post('/admin/pages', [
            'title' => 'About',
            'body' => '<p>Hello</p><script>alert(1)</script><img src=x onerror=alert(2)>',
            'is_published' => true,
        ])
        ->assertRedirect(route('admin.pages.index'));

    $body = (string) Page::query()->value('body_html');

    expect($body)->toContain('Hello')
        ->and($body)->not->toContain('<script')
        ->and($body)->not->toContain('onerror');
});

it('keeps slugs unique when titles collide', function () {
    Page::factory()->create(['slug' => 'about', 'title' => 'About']);

    actingAs(pageAdmin())
        ->post('/admin/pages', ['title' => 'About', 'is_published' => true])
        ->assertRedirect(route('admin.pages.index'));

    expect(Page::query()->where('title', 'About')->pluck('slug')->all())
        ->toContain('about', 'about-2');
});

it('updates and deletes a page', function () {
    $page = Page::factory()->create(['title' => 'Old', 'slug' => 'old']);

    actingAs(pageAdmin())
        ->put("/admin/pages/{$page->id}", [
            'title' => 'New title',
            'slug' => 'new-title',
            'body' => '<p>Updated</p>',
            'is_published' => false,
        ])
        ->assertRedirect(route('admin.pages.index'));

    expect($page->fresh()->title)->toBe('New title')
        ->and($page->fresh()->is_published)->toBeFalse();

    actingAs(pageAdmin())
        ->delete("/admin/pages/{$page->id}")
        ->assertRedirect(route('admin.pages.index'));

    expect(Page::query()->find($page->id))->toBeNull();
});

it('lists only published pages on the public api', function () {
    Page::factory()->create(['title' => 'Published', 'slug' => 'published', 'is_published' => true]);
    Page::factory()->draft()->create(['title' => 'Hidden', 'slug' => 'hidden']);

    $this->getJson('/api/v1/pages')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'published');
});

it('shows a published page and 404s a draft on the public api', function () {
    Page::factory()->create(['slug' => 'terms', 'title' => 'Terms', 'body_html' => '<p>Rules</p>']);
    Page::factory()->draft()->create(['slug' => 'secret', 'title' => 'Secret']);

    $this->getJson('/api/v1/pages/terms')
        ->assertOk()
        ->assertJsonPath('data.title', 'Terms')
        ->assertJsonPath('data.body_html', '<p>Rules</p>');

    $this->getJson('/api/v1/pages/secret')->assertNotFound();
    $this->getJson('/api/v1/pages/missing')->assertNotFound();
});
