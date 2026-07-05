<?php

declare(strict_types=1);

use App\Actions\Shipments\CreateConsignment;
use App\Jobs\PushOrderToCourier;
use App\Jobs\SyncCourierStatuses;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Courier\CustomerCourierStats;
use App\Services\Settings\SettingsService;
use App\Support\Courier\CourierManager;
use App\Support\Courier\FakeCourierGateway;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    cache()->flush();
    $this->courier = new FakeCourierGateway;
    // Resolve the Steadfast driver to the fake so no real HTTP is made.
    app(CourierManager::class)->register(Courier::DRIVER_STEADFAST, fn () => $this->courier);
});

// Configure the seeded default Steadfast courier with credentials so it can book.
function configuredSteadfast(): Courier
{
    $courier = Courier::query()->where('slug', 'steadfast')->firstOrFail();
    $courier->update([
        'is_active' => true,
        'is_default' => true,
        'config' => ['api_key' => 'test-key', 'secret_key' => 'test-secret'],
    ]);

    return $courier;
}

it('auto-pushes a confirmed order to the default API courier when configured', function () {
    $courier = configuredSteadfast();
    Bus::fake([PushOrderToCourier::class]);

    $order = Order::factory()->create(['status' => 'pending']);
    $order->update(['status' => 'confirmed']);

    Bus::assertDispatched(
        PushOrderToCourier::class,
        fn ($job) => $job->orderId === $order->id && $job->courierId === $courier->id,
    );
});

it('does not auto-push when the default courier has no credentials', function () {
    // Seeded steadfast exists as default, but its config is empty (unconfigured).
    Bus::fake([PushOrderToCourier::class]);

    $order = Order::factory()->create(['status' => 'pending']);
    $order->update(['status' => 'confirmed']);

    Bus::assertNotDispatched(PushOrderToCourier::class);
});

it('does not auto-push when there is no default courier', function () {
    configuredSteadfast()->update(['is_default' => false]);
    Bus::fake([PushOrderToCourier::class]);

    $order = Order::factory()->create(['status' => 'pending']);
    $order->update(['status' => 'confirmed']);

    Bus::assertNotDispatched(PushOrderToCourier::class);
});

it('does not auto-push when auto_push is switched off', function () {
    configuredSteadfast();
    app(SettingsService::class)->set('courier', 'auto_push', false, false);
    cache()->flush();
    Bus::fake([PushOrderToCourier::class]);

    $order = Order::factory()->create(['status' => 'pending']);
    $order->update(['status' => 'confirmed']);

    Bus::assertNotDispatched(PushOrderToCourier::class);
});

it('does not auto-push on an unrelated status change', function () {
    configuredSteadfast();
    Bus::fake([PushOrderToCourier::class]);

    $order = Order::factory()->create(['status' => 'confirmed']);
    $order->update(['status' => 'processing']);

    Bus::assertNotDispatched(PushOrderToCourier::class);
});

it('books exactly one consignment when the push job runs', function () {
    $courier = configuredSteadfast();
    $order = Order::factory()->create(['total' => 5000, 'advance_paid' => 2000]);

    (new PushOrderToCourier($order->id, $courier->id))->handle(app(CreateConsignment::class));

    $shipment = Shipment::query()->where('order_id', $order->id)->firstOrFail();
    expect($shipment->consignment_id)->toBe('CN-'.$shipment->id)
        ->and($shipment->courier_id)->toBe($courier->id)
        ->and($shipment->courier)->toBe('Steadfast')       // name snapshot
        ->and($shipment->cod_amount->toMinor())->toBe(300000) // 5000 - 2000
        ->and($this->courier->created)->toHaveCount(1);
});

it('records a manual courier without any API call', function () {
    $manual = Courier::factory()->manual()->create(['name' => 'Sundarban Courier']);
    $order = Order::factory()->create(['total' => 5000, 'advance_paid' => 0]);

    app(CreateConsignment::class)->handle($order, $manual);

    $shipment = Shipment::query()->where('order_id', $order->id)->firstOrFail();
    expect($shipment->courier)->toBe('Sundarban Courier')     // shown on the label
        ->and($shipment->consignment_id)->toBeNull()          // never called an API
        ->and($this->courier->created)->toHaveCount(0);
});

it('polls and updates in-flight consignments via their courier, skipping terminal ones', function () {
    $courier = configuredSteadfast();

    $order = Order::factory()->create();
    $inFlight = $order->shipment()->create([
        'courier_id' => $courier->id, 'courier' => $courier->name,
        'recipient_name' => 'Karim', 'recipient_phone' => '+8801712345678',
        'recipient_address' => 'Dhaka', 'tracking_code' => 'TRK00000001', 'status' => 'in_review',
    ]);

    $delivered = Order::factory()->create();
    $terminal = $delivered->shipment()->create([
        'courier_id' => $courier->id, 'courier' => $courier->name,
        'recipient_name' => 'Rahim', 'recipient_phone' => '+8801812345678',
        'recipient_address' => 'Dhaka', 'tracking_code' => 'TRK00000002', 'status' => 'delivered',
    ]);

    $this->courier->status = 'delivered';
    (new SyncCourierStatuses)->handle(app(CourierManager::class));

    expect($inFlight->fresh()->status)->toBe('delivered')       // polled + updated
        ->and($terminal->fresh()->status)->toBe('delivered');   // already terminal, untouched
});

it('skips manual-courier shipments when polling statuses', function () {
    $manual = Courier::factory()->manual()->create();
    $order = Order::factory()->create();
    $shipment = $order->shipment()->create([
        'courier_id' => $manual->id, 'courier' => $manual->name,
        'recipient_name' => 'Karim', 'recipient_phone' => '+8801712345678',
        'recipient_address' => 'Dhaka', 'tracking_code' => 'MANUAL-1', 'status' => 'in_review',
    ]);

    $this->courier->status = 'delivered';
    (new SyncCourierStatuses)->handle(app(CourierManager::class));

    // Manual courier has no API — status untouched by the poll.
    expect($shipment->fresh()->status)->toBe('in_review')
        ->and($this->courier->created)->toHaveCount(0);
});

it('computes a fraud/return-ratio score from courier history', function () {
    $phone = '+8801912345678';
    $mk = fn (string $status) => Order::factory()->create()->shipment()->create([
        'recipient_name' => 'Repeat Buyer', 'recipient_phone' => $phone,
        'recipient_address' => 'Dhaka', 'status' => $status,
    ]);

    $mk('delivered');
    $mk('cancelled');
    $mk('returned');
    $mk('in_review'); // in flight — not counted in completed

    $stats = app(CustomerCourierStats::class)->forPhone('01912345678'); // local form normalizes

    expect($stats['total'])->toBe(4)
        ->and($stats['delivered'])->toBe(1)
        ->and($stats['cancelled'])->toBe(1)
        ->and($stats['returned'])->toBe(1)
        ->and($stats['completed'])->toBe(3)
        ->and($stats['in_flight'])->toBe(1)
        ->and($stats['fraud_score'])->toBe(0.67) // (1 cancelled + 1 returned) / 3 completed
        ->and($stats['risk'])->toBe('high');
});

it('reports a new customer with no courier history', function () {
    $stats = app(CustomerCourierStats::class)->forPhone('01711111111');

    expect($stats['total'])->toBe(0)
        ->and($stats['risk'])->toBe('new')
        ->and($stats['fraud_score'])->toBe(0.0);
});
