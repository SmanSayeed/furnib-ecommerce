<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Marketing\FeedAccess;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

/**
 * Marketing → Facebook Commerce. The feed is off by default (a 404), and enabling
 * it mints an unguessable slug + a Basic-auth password shown exactly once. Gated
 * by marketing.manage.
 */
beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
    $this->marketer = User::factory()->create();
    $this->marketer->assignRole('marketer'); // marketing.manage
});

it('enables the feed and mints credentials shown once', function () {
    actingAs($this->marketer)
        ->post('/settings/facebook-commerce', ['feed_enabled' => true, 'feed_username' => 'furnib-feed'])
        ->assertRedirect()
        ->assertSessionHas('new_feed_password');

    $access = app(FeedAccess::class);

    expect($access->enabled())->toBeTrue()
        ->and($access->slug())->not->toBeNull()
        ->and($access->password())->not->toBeNull()
        ->and($access->url())->toContain('/products.csv');
});

it('does not re-mint the password on a second save', function () {
    $access = app(FeedAccess::class);

    actingAs($this->marketer)->post('/settings/facebook-commerce', ['feed_enabled' => true]);
    $first = $access->password();

    // A second save with the feed already on must NOT rotate the password.
    actingAs($this->marketer)
        ->post('/settings/facebook-commerce', ['feed_enabled' => true])
        ->assertSessionMissing('new_feed_password');

    expect($access->password())->toBe($first);
});

it('regenerates the slug and password', function () {
    $access = app(FeedAccess::class);
    actingAs($this->marketer)->post('/settings/facebook-commerce', ['feed_enabled' => true]);
    $oldSlug = $access->slug();
    $oldPass = $access->password();

    actingAs($this->marketer)
        ->post('/settings/facebook-commerce/regenerate')
        ->assertRedirect()
        ->assertSessionHas('new_feed_password');

    expect($access->slug())->not->toBe($oldSlug)
        ->and($access->password())->not->toBe($oldPass);
});

it('stores the feed password encrypted, not in plaintext', function () {
    actingAs($this->marketer)->post('/settings/facebook-commerce', ['feed_enabled' => true]);

    $plain = app(FeedAccess::class)->password();
    $raw = App\Models\Setting::query()
        ->where('group', FeedAccess::GROUP)->where('key', 'feed_password')->value('value');

    // The stored ciphertext must not equal the plaintext.
    expect($raw)->not->toBe($plain);
});

it('downloads a CSV export', function () {
    $response = actingAs($this->marketer)
        ->get('/settings/facebook-commerce/download')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment');
});

it('forbids managing Facebook Commerce without marketing.manage', function () {
    $user = User::factory()->create();
    $user->assignRole('sub-admin'); // no marketing.manage

    actingAs($user)->get('/settings/facebook-commerce')->assertForbidden();
    actingAs($user)->post('/settings/facebook-commerce', ['feed_enabled' => true])->assertForbidden();
});
