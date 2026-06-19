<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Support\Courier\CourierGateway;
use App\Support\Courier\FakeCourierGateway;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
    $this->courier = new FakeCourierGateway;
    $this->app->instance(CourierGateway::class, $this->courier);
});

function courierManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // orders.manage

    return $user;
}

function courierViewer(): User
{
    $user = User::factory()->create();
    $user->assignRole('sub-admin'); // orders.view only

    return $user;
}

it('books a consignment and stores the courier identifiers', function () {
    $order = Order::factory()->create(['total' => 5000, 'advance_paid' => 2000]);

    actingAs(courierManager())
        ->post("/admin/orders/{$order->id}/ship")
        ->assertRedirect();

    $shipment = Shipment::query()->where('order_id', $order->id)->firstOrFail();

    expect($shipment->consignment_id)->toBe('CN-'.$shipment->id)
        ->and($shipment->tracking_code)->toStartWith('TRK')
        ->and($shipment->status)->toBe('in_review')
        ->and($this->courier->created)->toHaveCount(1);
});

it('computes COD as the remaining balance (total minus paid)', function () {
    $order = Order::factory()->create(['total' => 5000, 'advance_paid' => 2000]);

    actingAs(courierManager())->post("/admin/orders/{$order->id}/ship")->assertRedirect();

    expect($order->shipment->cod_amount->toMinor())->toBe(300000); // 5000.00 - 2000.00
});

it('is idempotent — a second ship request does not rebook', function () {
    $order = Order::factory()->create();

    actingAs(courierManager())->post("/admin/orders/{$order->id}/ship")->assertRedirect();
    $first = $order->shipment->consignment_id;

    actingAs(courierManager())->post("/admin/orders/{$order->id}/ship")->assertRedirect();

    expect(Shipment::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and($order->fresh()->shipment->consignment_id)->toBe($first)
        ->and($this->courier->created)->toHaveCount(1);
});

it('maps a fetched tracking status onto the shipment', function () {
    $order = Order::factory()->create();
    $order->shipment()->create([
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
        ->post("/admin/orders/{$order->id}/ship")
        ->assertForbidden();

    expect(Shipment::query()->count())->toBe(0);
});

it('requires authentication', function () {
    $order = Order::factory()->create();

    $this->post("/admin/orders/{$order->id}/ship")->assertRedirect('/login');
});
