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
