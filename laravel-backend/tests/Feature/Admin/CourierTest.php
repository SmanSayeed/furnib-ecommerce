<?php

declare(strict_types=1);

use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Support\Courier\CourierManager;
use App\Support\Courier\FakeCourierGateway;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
    $this->courier = new FakeCourierGateway;
    app(CourierManager::class)->register(Courier::DRIVER_STEADFAST, fn () => $this->courier);

    // The seeded Steadfast courier, configured so it can book via the (fake) API.
    $this->steadfast = Courier::query()->where('slug', 'steadfast')->firstOrFail();
    $this->steadfast->update(['is_active' => true, 'config' => ['api_key' => 'k', 'secret_key' => 's']]);
});

function courierManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // orders.manage + couriers.manage

    return $user;
}

function courierViewer(): User
{
    $user = User::factory()->create();
    $user->assignRole('sub-admin'); // orders.view only

    return $user;
}

it('books a consignment with the chosen API courier and stores the identifiers', function () {
    $order = Order::factory()->create(['total' => 5000, 'advance_paid' => 2000]);

    actingAs(courierManager())
        ->post("/admin/orders/{$order->id}/ship", ['courier_id' => $this->steadfast->id])
        ->assertRedirect();

    $shipment = Shipment::query()->where('order_id', $order->id)->firstOrFail();

    expect($shipment->consignment_id)->toBe('CN-'.$shipment->id)
        ->and($shipment->tracking_code)->toStartWith('TRK')
        ->and($shipment->status)->toBe('in_review')
        ->and($shipment->courier_id)->toBe($this->steadfast->id)
        ->and($shipment->courier)->toBe('Steadfast')
        ->and($this->courier->created)->toHaveCount(1);
});

it('records a manual courier without calling any API', function () {
    $manual = Courier::factory()->manual()->create(['name' => 'SA Paribahan']);
    $order = Order::factory()->create();

    actingAs(courierManager())
        ->post("/admin/orders/{$order->id}/ship", ['courier_id' => $manual->id])
        ->assertRedirect();

    $shipment = Shipment::query()->where('order_id', $order->id)->firstOrFail();
    expect($shipment->courier)->toBe('SA Paribahan')
        ->and($shipment->consignment_id)->toBeNull()
        ->and($this->courier->created)->toHaveCount(0);
});

it('rejects booking an API courier that is not configured', function () {
    $this->steadfast->update(['config' => null]); // unconfigured
    $order = Order::factory()->create();

    actingAs(courierManager())
        ->post("/admin/orders/{$order->id}/ship", ['courier_id' => $this->steadfast->id])
        ->assertRedirect();

    // No shipment booked (the controller flashed an error and returned).
    expect(Shipment::query()->where('order_id', $order->id)->whereNotNull('consignment_id')->count())->toBe(0);
});

it('rejects an inactive courier selection', function () {
    $inactive = Courier::factory()->inactive()->create();
    $order = Order::factory()->create();

    actingAs(courierManager())
        ->post("/admin/orders/{$order->id}/ship", ['courier_id' => $inactive->id])
        ->assertSessionHasErrors('courier_id');
});

it('computes COD as the remaining balance (total minus paid)', function () {
    $order = Order::factory()->create(['total' => 5000, 'advance_paid' => 2000]);

    actingAs(courierManager())
        ->post("/admin/orders/{$order->id}/ship", ['courier_id' => $this->steadfast->id])
        ->assertRedirect();

    expect($order->shipment->cod_amount->toMinor())->toBe(300000); // 5000.00 - 2000.00
});

it('is idempotent — a second ship request does not rebook', function () {
    $order = Order::factory()->create();

    actingAs(courierManager())->post("/admin/orders/{$order->id}/ship", ['courier_id' => $this->steadfast->id])->assertRedirect();
    $first = $order->shipment->consignment_id;

    actingAs(courierManager())->post("/admin/orders/{$order->id}/ship", ['courier_id' => $this->steadfast->id])->assertRedirect();

    expect(Shipment::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and($order->fresh()->shipment->consignment_id)->toBe($first)
        ->and($this->courier->created)->toHaveCount(1);
});

it('maps a fetched tracking status onto the shipment', function () {
    $order = Order::factory()->create();
    $order->shipment()->create([
        'courier_id' => $this->steadfast->id,
        'courier' => 'Steadfast',
        'recipient_name' => 'Karim',
        'recipient_phone' => '+8801712345678',
        'recipient_address' => 'Dhaka',
        'tracking_code' => 'TRK00000001',
        'status' => 'in_review',
    ]);
    $this->courier->status = 'delivered';

    actingAs(courierManager())->post("/admin/orders/{$order->id}/track")->assertRedirect();

    expect($order->fresh()->shipment->status)->toBe('delivered');
});

it('forbids staff without orders.manage from booking', function () {
    $order = Order::factory()->create();

    actingAs(courierViewer())
        ->post("/admin/orders/{$order->id}/ship", ['courier_id' => $this->steadfast->id])
        ->assertForbidden();

    expect(Shipment::query()->count())->toBe(0);
});

it('requires authentication', function () {
    $order = Order::factory()->create();

    $this->post("/admin/orders/{$order->id}/ship")->assertRedirect('/login');
});

it('snapshots the RedX delivery area onto the shipment meta', function () {
    // Fake the RedX API so no real HTTP is made, but the courier is "configured".
    app(CourierManager::class)->register(Courier::DRIVER_REDX, fn () => $this->courier);
    $redx = Courier::factory()->create([
        'name' => 'RedX',
        'driver' => 'redx',
        'is_active' => true,
        'config' => ['access_token' => 't', 'pickup_store_id' => '1'],
    ]);
    $order = Order::factory()->create();

    actingAs(courierManager())
        ->post("/admin/orders/{$order->id}/ship", [
            'courier_id' => $redx->id,
            'delivery_area_id' => 42,
            'delivery_area' => 'Banani',
        ])
        ->assertRedirect();

    $shipment = Shipment::query()->where('order_id', $order->id)->firstOrFail();
    expect($shipment->meta['delivery_area_id'])->toBe(42)
        ->and($shipment->meta['delivery_area'])->toBe('Banani')
        ->and($shipment->courier)->toBe('RedX');
});

it('rejects a RedX booking without a delivery area', function () {
    app(CourierManager::class)->register(Courier::DRIVER_REDX, fn () => $this->courier);
    $redx = Courier::factory()->create([
        'driver' => 'redx',
        'is_active' => true,
        'config' => ['access_token' => 't', 'pickup_store_id' => '1'],
    ]);
    $order = Order::factory()->create();

    actingAs(courierManager())
        ->post("/admin/orders/{$order->id}/ship", ['courier_id' => $redx->id])
        ->assertSessionHasErrors(['delivery_area_id', 'delivery_area']);

    expect(Shipment::query()->where('order_id', $order->id)->count())->toBe(0);
});

it('snapshots the Pathao city/zone/area onto the shipment meta', function () {
    app(CourierManager::class)->register(Courier::DRIVER_PATHAO, fn () => $this->courier);
    $pathao = Courier::factory()->create([
        'name' => 'Pathao',
        'driver' => 'pathao',
        'is_active' => true,
        'config' => [
            'client_id' => 'c', 'client_secret' => 's',
            'username' => 'u', 'password' => 'p', 'store_id' => '9',
        ],
    ]);
    $order = Order::factory()->create();

    actingAs(courierManager())
        ->post("/admin/orders/{$order->id}/ship", [
            'courier_id' => $pathao->id,
            'recipient_city' => 1,
            'recipient_zone' => 5,
            'recipient_area' => 9,
        ])
        ->assertRedirect();

    $shipment = Shipment::query()->where('order_id', $order->id)->firstOrFail();
    expect($shipment->meta)->toBe([
        'recipient_city' => 1,
        'recipient_zone' => 5,
        'recipient_area' => 9,
    ]);
});
