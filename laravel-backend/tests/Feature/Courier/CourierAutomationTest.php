<?php

declare(strict_types=1);

use App\Actions\Shipments\CreateConsignment;
use App\Jobs\PushOrderToCourier;
use App\Jobs\SyncCourierStatuses;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Courier\CustomerCourierStats;
use App\Services\Settings\SettingsService;
use App\Support\Courier\CourierGateway;
use App\Support\Courier\FakeCourierGateway;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    cache()->flush();
    $this->courier = new FakeCourierGateway;
    $this->app->instance(CourierGateway::class, $this->courier);
});

function configureCourier(): void
{
    $settings = app(SettingsService::class);
    $settings->set('steadfast', 'api_key', 'test-key', true);
    $settings->set('steadfast', 'secret_key', 'test-secret', true);
    cache()->flush();
}

it('auto-pushes a confirmed order to the courier when configured', function () {
    configureCourier();
    Bus::fake([PushOrderToCourier::class]);

    $order = Order::factory()->create(['status' => 'pending']);
    $order->update(['status' => 'confirmed']);

    Bus::assertDispatched(PushOrderToCourier::class, fn ($job) => $job->orderId === $order->id);
});

it('does not auto-push when no courier is configured', function () {
    Bus::fake([PushOrderToCourier::class]);

    $order = Order::factory()->create(['status' => 'pending']);
    $order->update(['status' => 'confirmed']);

    Bus::assertNotDispatched(PushOrderToCourier::class);
});

it('does not auto-push when auto_push is switched off', function () {
    configureCourier();
    app(SettingsService::class)->set('steadfast', 'auto_push', false, false);
    cache()->flush();
    Bus::fake([PushOrderToCourier::class]);

    $order = Order::factory()->create(['status' => 'pending']);
    $order->update(['status' => 'confirmed']);

    Bus::assertNotDispatched(PushOrderToCourier::class);
});

it('does not auto-push on an unrelated status change', function () {
    configureCourier();
    Bus::fake([PushOrderToCourier::class]);

    $order = Order::factory()->create(['status' => 'confirmed']);
    $order->update(['status' => 'processing']);

    Bus::assertNotDispatched(PushOrderToCourier::class);
});

it('books exactly one consignment when the push job runs', function () {
    $order = Order::factory()->create(['total' => 5000, 'advance_paid' => 2000]);

    (new PushOrderToCourier($order->id))->handle(app(CreateConsignment::class));

    $shipment = Shipment::query()->where('order_id', $order->id)->firstOrFail();
    expect($shipment->consignment_id)->toBe('CN-'.$shipment->id)
        ->and($shipment->cod_amount->toMinor())->toBe(300000) // 5000 - 2000
        ->and($this->courier->created)->toHaveCount(1);
});

it('polls and updates in-flight consignments, skipping terminal ones', function () {
    $order = Order::factory()->create();
    $inFlight = $order->shipment()->create([
        'recipient_name' => 'Karim', 'recipient_phone' => '+8801712345678',
        'recipient_address' => 'Dhaka', 'tracking_code' => 'TRK00000001', 'status' => 'in_review',
    ]);

    $delivered = Order::factory()->create();
    $terminal = $delivered->shipment()->create([
        'recipient_name' => 'Rahim', 'recipient_phone' => '+8801812345678',
        'recipient_address' => 'Dhaka', 'tracking_code' => 'TRK00000002', 'status' => 'delivered',
    ]);

    $this->courier->status = 'delivered';
    (new SyncCourierStatuses)->handle($this->courier);

    expect($inFlight->fresh()->status)->toBe('delivered')       // polled + updated
        ->and($terminal->fresh()->status)->toBe('delivered');   // already terminal, untouched
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
