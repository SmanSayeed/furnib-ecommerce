<?php

declare(strict_types=1);

use App\Services\Settings\SettingsService;

it('adds conservative security headers to API responses', function () {
    $this->get('/api/v1/health')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('allows the storefront origin through CORS', function () {
    config(['cors.allowed_origins' => ['https://shop.furnib.test']]);

    $this->get('/api/v1/health', ['Origin' => 'https://shop.furnib.test'])
        ->assertHeader('Access-Control-Allow-Origin', 'https://shop.furnib.test');
});

it('does not grant CORS to a non-storefront origin', function () {
    config(['cors.allowed_origins' => ['https://shop.furnib.test']]);

    $response = $this->get('/api/v1/health', ['Origin' => 'https://evil.example.com']);

    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example.com');
});

it('never leaks any configured secret through a public endpoint', function () {
    $settings = app(SettingsService::class);
    $secrets = [
        ['marketing', 'fb_capi_token', 'SECRET-capi-token'],
        ['smtp', 'password', 'SECRET-smtp-pass'],
        ['sslcommerz', 'store_passwd', 'SECRET-ssl-pass'],
        ['steadfast', 'secret_key', 'SECRET-courier-key'],
    ];
    foreach ($secrets as [$group, $key, $value]) {
        $settings->set($group, $key, $value, isSecret: true);
    }

    // NB: the product feed is deliberately NOT in this list — it is no longer a
    // public endpoint (Basic-auth + unguessable path); see ProductFeedTest.
    $publicEndpoints = [
        '/api/v1/marketing',
        '/api/v1/settings',
        '/api/v1/maintenance',
        '/sitemap.xml',
        '/robots.txt',
    ];

    foreach ($publicEndpoints as $endpoint) {
        $content = $this->get($endpoint)->assertOk()->getContent();

        foreach ($secrets as [, , $value]) {
            expect(str_contains((string) $content, $value))->toBeFalse();
        }
    }
});
