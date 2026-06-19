<?php

declare(strict_types=1);

use App\Models\Visitor;
use App\Support\Utm;

beforeEach(fn () => cache()->flush());

it('records a pageview with server-side IP and user agent', function () {
    $this->postJson('/api/v1/track', [
        'path' => '/products/teak-sofa',
        'session_id' => 'sess-1',
    ])->assertOk()->assertJson(['recorded' => true]);

    $visit = Visitor::query()->firstOrFail();
    expect($visit->path)->toBe('/products/teak-sofa')
        ->and($visit->session_id)->toBe('sess-1')
        ->and($visit->ip)->not->toBeNull();
});

it('captures UTM parameters from the supplied url', function () {
    $this->postJson('/api/v1/track', [
        'path' => '/',
        'url' => 'https://furnib.test/?utm_source=facebook&utm_medium=cpc&utm_campaign=eid',
    ])->assertOk();

    $visit = Visitor::query()->firstOrFail();
    expect($visit->utm_source)->toBe('facebook')
        ->and($visit->utm_medium)->toBe('cpc')
        ->and($visit->utm_campaign)->toBe('eid');
});

it('stores no PII fields sent in the body', function () {
    $this->postJson('/api/v1/track', [
        'path' => '/',
        'name' => 'Karim',          // not whitelisted
        'email' => 'k@example.com',  // not whitelisted
    ])->assertOk();

    $attributes = Visitor::query()->firstOrFail()->getAttributes();
    expect($attributes)->not->toHaveKey('name')
        ->and($attributes)->not->toHaveKey('email');
});

it('parses UTM from a URL or bare query string', function () {
    expect(Utm::parse('https://x.test/page?utm_source=ig&utm_medium=story'))
        ->toMatchArray(['utm_source' => 'ig', 'utm_medium' => 'story', 'utm_campaign' => null]);

    expect(Utm::parse('utm_source=newsletter&utm_campaign=launch'))
        ->toMatchArray(['utm_source' => 'newsletter', 'utm_campaign' => 'launch']);

    expect(Utm::parse(null))
        ->toMatchArray(['utm_source' => null, 'utm_medium' => null, 'utm_campaign' => null]);
});
