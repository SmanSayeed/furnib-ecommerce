<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Shipment;
use App\Support\Courier\RedxCourier;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function redxShipment(array $meta): Shipment
{
    $order = Order::factory()->create();

    return $order->shipment()->create([
        'courier' => 'RedX',
        'recipient_name' => 'Karim Uddin',
        'recipient_phone' => '01712345678',
        'recipient_address' => 'House 1, Road 2, Banani, Dhaka',
        'cod_amount' => Money::fromMinor(150000), // ৳1,500
        'status' => 'pending',
        'meta' => $meta,
    ]);
}

it('creates a RedX parcel from booking meta and returns the tracking id', function () {
    Http::fake([
        '*/parcel' => Http::response(['tracking_id' => 'RDX-778899'], 200),
    ]);

    $shipment = redxShipment(['delivery_area_id' => 12, 'delivery_area' => 'Banani']);

    $result = (new RedxCourier('token-abc', '99', sandbox: true))->createConsignment($shipment);

    expect($result)->toBe([
        'consignment_id' => 'RDX-778899',
        'tracking_code' => 'RDX-778899',
        'status' => 'pending',
    ]);

    Http::assertSent(function ($request) use ($shipment) {
        return Str::contains($request->url(), 'sandbox.redx.com.bd/v1.0.0-beta/parcel')
            && $request->hasHeader('API-ACCESS-TOKEN', 'Bearer token-abc')
            && $request['delivery_area_id'] === 12
            && $request['delivery_area'] === 'Banani'
            && $request['pickup_store_id'] === 99
            && $request['cash_collection_amount'] === '1500'
            && $request['merchant_invoice_id'] === $shipment->order->order_no;
    });
});

it('hits the live host when sandbox is off', function () {
    Http::fake(['*/parcel' => Http::response(['tracking_id' => 'RDX-1'], 200)]);

    (new RedxCourier('t', '1', sandbox: false))
        ->createConsignment(redxShipment(['delivery_area_id' => 1, 'delivery_area' => 'X']));

    Http::assertSent(fn ($r) => Str::contains($r->url(), 'openapi.redx.com.bd'));
});

it('refuses to book when no delivery area was selected', function () {
    Http::fake();
    $shipment = redxShipment([]); // no area

    expect(fn () => (new RedxCourier('t', '1'))->createConsignment($shipment))
        ->toThrow(RuntimeException::class);

    Http::assertNothingSent();
});

it('refuses to book without an access token', function () {
    Http::fake();
    $shipment = redxShipment(['delivery_area_id' => 1, 'delivery_area' => 'X']);

    expect(fn () => (new RedxCourier(null, '1'))->createConsignment($shipment))
        ->toThrow(RuntimeException::class);

    Http::assertNothingSent();
});

it('reads the latest tracking event as the status', function () {
    Http::fake([
        '*/parcel/track/*' => Http::response([
            'tracking' => [
                ['message_en' => 'Delivered', 'time' => 't2'],
                ['message_en' => 'Picked up', 'time' => 't1'],
            ],
        ], 200),
    ]);

    expect((new RedxCourier('t', '1'))->getStatus('RDX-1'))->toBe('Delivered');
});

it('lists delivery areas for the booking selector', function () {
    Http::fake([
        '*/areas' => Http::response([
            'areas' => [
                ['id' => 1, 'name' => 'Banani', 'post_code' => '1213'],
                ['id' => 2, 'name' => 'Gulshan', 'post_code' => '1212'],
            ],
        ], 200),
    ]);

    $areas = (new RedxCourier('t', '1'))->areas();

    expect($areas)->toHaveCount(2)
        ->and($areas[0]['id'])->toBe(1)
        ->and($areas[0]['name'])->toContain('Banani');
});
