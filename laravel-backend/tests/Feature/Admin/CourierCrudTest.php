<?php

declare(strict_types=1);

use App\Models\Courier;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function courierAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // has couriers.manage

    return $user;
}

it('lists couriers for a manager', function () {
    actingAs(courierAdmin())
        ->get('/admin/shipping/couriers')
        ->assertOk();
});

it('blocks users without couriers.manage', function () {
    $user = User::factory()->create();
    $user->assignRole('manager'); // no couriers.manage

    actingAs($user)->get('/admin/shipping/couriers')->assertForbidden();
    actingAs($user)->post('/admin/shipping/couriers', ['name' => 'X', 'driver' => 'manual'])->assertForbidden();
});

it('creates a manual courier with an auto slug and no config', function () {
    actingAs(courierAdmin())
        ->post('/admin/shipping/couriers', [
            'name' => 'Sundarban Courier',
            'driver' => 'manual',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.couriers.index'));

    $courier = Courier::query()->where('name', 'Sundarban Courier')->firstOrFail();
    expect($courier->slug)->toBe('sundarban-courier')
        ->and($courier->driver)->toBe('manual')
        ->and($courier->config)->toBeNull()
        ->and($courier->isConfigured())->toBeTrue(); // manual is always usable
});

it('creates a steadfast courier and stores encrypted credentials', function () {
    actingAs(courierAdmin())
        ->post('/admin/shipping/couriers', [
            'name' => 'Steadfast Live',
            'driver' => 'steadfast',
            'is_active' => true,
            'api_key' => 'live-key',
            'secret_key' => 'live-secret',
        ])
        ->assertRedirect();

    $courier = Courier::query()->where('name', 'Steadfast Live')->firstOrFail();
    expect($courier->credential('api_key'))->toBe('live-key')
        ->and($courier->credential('secret_key'))->toBe('live-secret')
        ->and($courier->isConfigured())->toBeTrue();

    // Stored encrypted — the raw DB column is not the plaintext secret.
    $raw = (string) $courier->getRawOriginal('config');
    expect($raw)->not->toContain('live-secret');
});

it('keeps the stored secret when the credential field is left blank on update', function () {
    $courier = Courier::factory()->steadfast(['api_key' => 'old-key', 'secret_key' => 'old-secret'])->create();

    actingAs(courierAdmin())
        ->put("/admin/shipping/couriers/{$courier->id}", [
            'name' => 'Steadfast',
            'driver' => 'steadfast',
            'is_active' => true,
            'api_key' => 'new-key',
            'secret_key' => '', // blank keeps the old secret
        ])
        ->assertRedirect();

    $courier->refresh();
    expect($courier->credential('api_key'))->toBe('new-key')
        ->and($courier->credential('secret_key'))->toBe('old-secret');
});

it('never exposes secrets to the browser on the edit page', function () {
    $courier = Courier::factory()->steadfast(['api_key' => 'k', 'secret_key' => 'topsecret'])->create();

    $res = actingAs(courierAdmin())->get("/admin/shipping/couriers/{$courier->id}/edit")->assertOk();

    expect($res->getContent())->not->toContain('topsecret');
    $props = $res->viewData('page')['props']['courier'];
    expect($props['api_key_set'])->toBeTrue()
        ->and($props['secret_key_set'])->toBeTrue()
        ->and($props)->not->toHaveKey('api_key');
});

it('enforces a single default courier', function () {
    $first = Courier::factory()->default()->create(['name' => 'First']);
    $second = Courier::factory()->create(['name' => 'Second']);

    actingAs(courierAdmin())
        ->put("/admin/shipping/couriers/{$second->id}", [
            'name' => 'Second',
            'driver' => 'manual',
            'is_active' => true,
            'is_default' => true,
        ])
        ->assertRedirect();

    expect($second->fresh()->is_default)->toBeTrue()
        ->and($first->fresh()->is_default)->toBeFalse(); // demoted
});

it('rejects an unsupported driver', function () {
    actingAs(courierAdmin())
        ->post('/admin/shipping/couriers', ['name' => 'X', 'driver' => 'fedex'])
        ->assertSessionHasErrors('driver'); // not a known courier driver
});

it('creates a RedX courier storing encrypted credentials + sandbox flag', function () {
    actingAs(courierAdmin())
        ->post('/admin/shipping/couriers', [
            'name' => 'RedX',
            'driver' => 'redx',
            'is_active' => true,
            'sandbox' => true,
            'access_token' => 'jwt-token',
            'pickup_store_id' => '4242',
        ])
        ->assertRedirect();

    $courier = Courier::query()->where('name', 'RedX')->firstOrFail();
    expect($courier->credential('access_token'))->toBe('jwt-token')
        ->and($courier->credential('pickup_store_id'))->toBe('4242')
        ->and($courier->config['sandbox'])->toBeTrue()
        ->and($courier->isConfigured())->toBeTrue();

    // Token never lands in the DB as plaintext.
    expect((string) $courier->getRawOriginal('config'))->not->toContain('jwt-token');
});

it('creates a Pathao courier and reports unconfigured until every credential is set', function () {
    actingAs(courierAdmin())
        ->post('/admin/shipping/couriers', [
            'name' => 'Pathao',
            'driver' => 'pathao',
            'is_active' => true,
            'client_id' => 'cid',
            'client_secret' => 'csecret',
            'username' => 'merchant@shop.test',
            // password + store_id intentionally omitted
        ])
        ->assertRedirect();

    $courier = Courier::query()->where('name', 'Pathao')->firstOrFail();
    expect($courier->credential('client_id'))->toBe('cid')
        ->and($courier->isConfigured())->toBeFalse(); // missing password + store_id

    actingAs(courierAdmin())
        ->put("/admin/shipping/couriers/{$courier->id}", [
            'name' => 'Pathao',
            'driver' => 'pathao',
            'is_active' => true,
            'password' => 'secret',
            'store_id' => '77',
        ])
        ->assertRedirect();

    expect($courier->fresh()->isConfigured())->toBeTrue()
        ->and($courier->fresh()->credential('client_id'))->toBe('cid'); // blank fields kept
});

it('soft-deletes a courier, keeping historical shipments intact', function () {
    $courier = Courier::factory()->manual()->create();

    actingAs(courierAdmin())
        ->delete("/admin/shipping/couriers/{$courier->id}")
        ->assertRedirect(route('admin.couriers.index'));

    expect(Courier::query()->find($courier->id))->toBeNull()
        ->and(Courier::withTrashed()->find($courier->id))->not->toBeNull();
});
