<?php

declare(strict_types=1);

use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->settings = app(SettingsService::class);
});

it('returns the default when a setting is missing', function () {
    expect($this->settings->get('contact', 'whatsapp', '+8800000'))->toBe('+8800000');
});

it('round-trips a boolean with its type preserved', function () {
    $this->settings->set('home', 'show_banner', true);

    expect($this->settings->get('home', 'show_banner'))->toBeTrue();
});

it('round-trips an integer and an array', function () {
    $this->settings->set('home', 'columns', 2);
    $this->settings->set('seo', 'keywords', ['furniture', 'chair']);

    expect($this->settings->get('home', 'columns'))->toBe(2)
        ->and($this->settings->get('seo', 'keywords'))->toBe(['furniture', 'chair']);
});

it('stores secret values as ciphertext but reads them back in clear', function () {
    $this->settings->set('payment', 'sslcommerz_secret', 'super-secret-key', isSecret: true);

    $rawStored = DB::table('settings')->where('key', 'sslcommerz_secret')->value('value');

    expect($rawStored)->not->toBe('super-secret-key')
        ->and($this->settings->get('payment', 'sslcommerz_secret'))->toBe('super-secret-key');
});

it('masks secret values in the group array unless explicitly included', function () {
    $this->settings->set('payment', 'store_id', 'public-store', isSecret: false);
    $this->settings->set('payment', 'api_secret', 'hidden', isSecret: true);

    $public = $this->settings->toArray('payment');
    $full = $this->settings->toArray('payment', includeSecrets: true);

    expect($public['store_id'])->toBe('public-store')
        ->and($public['api_secret'])->toBeNull()
        ->and($full['api_secret'])->toBe('hidden');
});
