<?php

declare(strict_types=1);

use App\Models\NewsletterSubscriber;

it('subscribes a new email', function () {
    $this->postJson('/api/v1/newsletter', ['email' => 'Karim@Example.com'])
        ->assertCreated();

    expect(NewsletterSubscriber::query()->count())->toBe(1)
        ->and(NewsletterSubscriber::query()->first()->email)->toBe('karim@example.com');
});

it('does not create a duplicate for an already-subscribed email', function () {
    NewsletterSubscriber::query()->create(['email' => 'karim@example.com']);

    $this->postJson('/api/v1/newsletter', ['email' => 'karim@example.com'])
        ->assertOk();

    expect(NewsletterSubscriber::query()->count())->toBe(1);
});

it('rejects a malformed email', function () {
    $this->postJson('/api/v1/newsletter', ['email' => 'not-an-email'])
        ->assertStatus(422);

    expect(NewsletterSubscriber::query()->count())->toBe(0);
});

it('rejects a missing email', function () {
    $this->postJson('/api/v1/newsletter', [])
        ->assertStatus(422);
});
