<?php

declare(strict_types=1);

use App\Models\Courier;
use App\Services\Settings\SettingsService;
use App\Support\Courier\CourierManager;

/**
 * THE BUG: courier credentials live in TWO stores —
 *   - couriers.config              (Shipping → Couriers)
 *   - the legacy `steadfast` settings group (Settings → Integrations)
 *
 * The driver factory has always read BOTH, but the gate that decides whether the
 * Book button is enabled — Courier::isConfigured() — read only the first. So an
 * owner who entered their keys under Settings → Integrations saw "Configured" on
 * that page and "needs credentials" on the order page, with booking dead in the
 * middle. The keys were fine the whole time.
 */
beforeEach(function () {
    $this->settings = app(SettingsService::class);
});

function steadfastCourier(?array $config = null): Courier
{
    return Courier::query()->create([
        'name' => 'Steadfast',
        'slug' => 'steadfast-'.uniqid(),
        'driver' => Courier::DRIVER_STEADFAST,
        'is_active' => true,
        'is_default' => false,
        'position_order' => 0,
        'config' => $config,
    ]);
}

it('reports configured when the keys live only in the legacy settings group', function () {
    // Exactly the owner's situation.
    $this->settings->set('steadfast', 'api_key', 'legacy-api-key', true);
    $this->settings->set('steadfast', 'secret_key', 'legacy-secret', true);

    $courier = steadfastCourier(null);

    expect($courier->isConfigured())->toBeTrue()
        ->and($courier->credential('api_key'))->toBe('legacy-api-key')
        ->and(app(CourierManager::class)->canBookViaApi($courier))->toBeTrue();
});

it('reports configured when the keys live in the courier config', function () {
    $courier = steadfastCourier(['api_key' => 'cfg-key', 'secret_key' => 'cfg-secret']);

    expect($courier->isConfigured())->toBeTrue()
        ->and($courier->credential('api_key'))->toBe('cfg-key');
});

it('prefers the courier config over the legacy settings group', function () {
    $this->settings->set('steadfast', 'api_key', 'legacy-api-key', true);
    $this->settings->set('steadfast', 'secret_key', 'legacy-secret', true);

    $courier = steadfastCourier(['api_key' => 'cfg-key', 'secret_key' => 'cfg-secret']);

    expect($courier->credential('api_key'))->toBe('cfg-key');
});

it('falls back per key, not all or nothing', function () {
    // config has the api_key but not the secret — the secret still resolves from
    // the legacy group, so the courier is usable.
    $this->settings->set('steadfast', 'secret_key', 'legacy-secret', true);

    $courier = steadfastCourier(['api_key' => 'cfg-key']);

    expect($courier->credential('api_key'))->toBe('cfg-key')
        ->and($courier->credential('secret_key'))->toBe('legacy-secret')
        ->and($courier->isConfigured())->toBeTrue();
});

it('reports not configured when the keys exist nowhere', function () {
    $courier = steadfastCourier(null);

    expect($courier->isConfigured())->toBeFalse()
        ->and(app(CourierManager::class)->canBookViaApi($courier))->toBeFalse();
});

it('gives a non-steadfast driver no legacy fallback', function () {
    // The legacy settings group is Steadfast-only — RedX must not accidentally
    // pick up a Steadfast key.
    $this->settings->set('steadfast', 'api_key', 'legacy-api-key', true);

    $courier = Courier::query()->create([
        'name' => 'RedX', 'slug' => 'redx-'.uniqid(), 'driver' => Courier::DRIVER_REDX,
        'is_active' => true, 'is_default' => false, 'position_order' => 0, 'config' => null,
    ]);

    expect($courier->isConfigured())->toBeFalse();
});

it('treats an undecryptable config as empty instead of throwing', function () {
    // Simulates an APP_KEY mismatch (DB moved between environments, key rotated).
    // Reading the encrypted:array cast would normally throw and 500 the page.
    $courier = steadfastCourier(['api_key' => 'cfg-key', 'secret_key' => 'cfg-secret']);
    Courier::query()->whereKey($courier->id)->update(['config' => 'not-a-valid-ciphertext']);

    $fresh = Courier::query()->findOrFail($courier->id);

    expect($fresh->safeConfig())->toBe([])
        ->and($fresh->isConfigured())->toBeFalse();
});
