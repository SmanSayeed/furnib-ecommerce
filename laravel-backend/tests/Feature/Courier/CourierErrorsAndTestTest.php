<?php

declare(strict_types=1);

use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Courier\CourierException;
use App\Support\Courier\CourierManager;
use Database\Seeders\PermissionRoleSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

/**
 * Every SteadFast failure used to collapse into "Failed to create SteadFast
 * consignment." — a 401 from a wrong key, a 422 from a duplicate invoice, and a
 * blocked firewall were indistinguishable, and the un-caught exception hit the
 * admin as a white 500 page. These tests pin the provider's real answer reaching
 * the admin, and the "Test connection" button that finally makes a credential
 * verifiable without shipping anything.
 */
beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('owner'); // couriers.manage + orders.manage
});

function sfWithKeys(): Courier
{
    return Courier::query()->create([
        'name' => 'Steadfast',
        'slug' => 'steadfast-'.uniqid(),
        'driver' => Courier::DRIVER_STEADFAST,
        'is_active' => true,
        'is_default' => false,
        'position_order' => 0,
        'config' => ['api_key' => 'k', 'secret_key' => 's'],
    ]);
}

function sfBookableOrder(): Order
{
    $order = Order::factory()->create(['status' => 'confirmed']);
    OrderItem::factory()->create(['order_id' => $order->id]);

    return $order;
}

// ─── The adapter names the real failure ───────────────────────────────────────

it('raises a CourierException carrying the status when the provider rejects the credentials', function () {
    Http::fake(['portal.packzy.com/*' => Http::response('Unauthorized', 401)]);

    $driver = app(CourierManager::class)->driverFor(sfWithKeys());

    expect(fn () => $driver->testConnection())
        ->toThrow(CourierException::class);
});

it('includes the provider body and an IP-whitelist hint on a 401', function () {
    Http::fake(['portal.packzy.com/*' => Http::response('Invalid API key', 401)]);

    $driver = app(CourierManager::class)->driverFor(sfWithKeys());

    try {
        $driver->testConnection();
    } catch (CourierException $e) {
        expect($e->getMessage())
            ->toContain('HTTP 401')
            ->toContain('Invalid API key')
            ->toContain('whitelisted');   // the usual cause on a fresh VPS

        return;
    }

    $this->fail('Expected a CourierException.');
});

it('reports the provider as unreachable instead of hanging', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    $driver = app(CourierManager::class)->driverFor(sfWithKeys());

    try {
        $driver->testConnection();
    } catch (CourierException $e) {
        expect($e->getMessage())->toContain('Could not reach SteadFast');

        return;
    }

    $this->fail('Expected a CourierException.');
});

it('names a missing credential rather than failing obscurely', function () {
    $courier = Courier::query()->create([
        'name' => 'Steadfast', 'slug' => 'sf-blank-'.uniqid(), 'driver' => Courier::DRIVER_STEADFAST,
        'is_active' => true, 'is_default' => false, 'position_order' => 0, 'config' => null,
    ]);

    $driver = app(CourierManager::class)->driverFor($courier);

    expect(fn () => $driver->testConnection())
        ->toThrow(CourierException::class, 'no API credentials');
});

// ─── Booking surfaces the message instead of 500-ing ──────────────────────────

it('flashes the provider message instead of a 500 when booking fails', function () {
    Http::fake(['portal.packzy.com/*' => Http::response('Duplicate invoice', 422)]);

    $courier = sfWithKeys();
    $order = sfBookableOrder();

    actingAs($this->owner)
        ->post("/admin/orders/{$order->id}/ship", ['courier_id' => $courier->id])
        ->assertRedirect();          // not a 500

    expect($order->refresh()->shipment)->toBeNull();
});

// ─── Test connection ──────────────────────────────────────────────────────────

it('reports the balance when the credentials are valid', function () {
    Http::fake(['portal.packzy.com/api/v1/get_balance' => Http::response([
        'status' => 200, 'current_balance' => 1240.5,
    ])]);

    $message = app(CourierManager::class)->driverFor(sfWithKeys())->testConnection();

    expect($message)->toContain('SteadFast connected')
        ->toContain('1,240.50');
});

it('lets an authorized admin test a courier from the couriers page', function () {
    Http::fake(['portal.packzy.com/*' => Http::response(['current_balance' => 500])]);

    $courier = sfWithKeys();

    actingAs($this->owner)
        ->post("/admin/shipping/couriers/{$courier->id}/test")
        ->assertRedirect();
});

it('explains that a manual courier has no API to test', function () {
    $courier = Courier::query()->create([
        'name' => 'Local rider', 'slug' => 'manual-'.uniqid(), 'driver' => 'manual',
        'is_active' => true, 'is_default' => false, 'position_order' => 0, 'config' => null,
    ]);

    actingAs($this->owner)
        ->post("/admin/shipping/couriers/{$courier->id}/test")
        ->assertRedirect();
});

it('forbids testing a courier without couriers.manage', function () {
    $courier = sfWithKeys();
    $user = User::factory()->create();
    $user->assignRole('marketer');

    actingAs($user)
        ->post("/admin/shipping/couriers/{$courier->id}/test")
        ->assertForbidden();
});
