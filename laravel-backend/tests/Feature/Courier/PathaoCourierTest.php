<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Shipment;
use App\Support\Courier\PathaoCourier;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    cache()->flush(); // token cache is per-courier; start clean
});

function pathao(string $cacheKey = 'test:pathao:token'): PathaoCourier
{
    return new PathaoCourier(
        clientId: 'cid',
        clientSecret: 'csecret',
        username: 'merchant@shop.test',
        password: 'secret',
        storeId: '77',
        sandbox: true,
        cacheKey: $cacheKey,
    );
}

function pathaoShipment(array $meta): Shipment
{
    $order = Order::factory()->create();

    return $order->shipment()->create([
        'courier' => 'Pathao',
        'recipient_name' => 'Karim Uddin',
        'recipient_phone' => '01712345678',
        'recipient_address' => 'House 1, Road 2, Banani, Dhaka',
        'cod_amount' => Money::fromMinor(200000), // ৳2,000
        'status' => 'pending',
        'meta' => $meta,
    ]);
}

function fakeToken(): void
{
    Http::fake([
        '*/issue-token' => Http::response(['access_token' => 'ACCESS-TOKEN', 'expires_in' => 3600], 200),
        '*/orders' => Http::response(['data' => ['consignment_id' => 'DA-90210', 'order_status' => 'Pending']], 200),
        '*/city-list' => Http::response(['data' => ['data' => [['city_id' => 1, 'city_name' => 'Dhaka']]]], 200),
        '*/zone-list' => Http::response(['data' => ['data' => [['zone_id' => 5, 'zone_name' => 'Banani']]]], 200),
        '*/area-list' => Http::response(['data' => ['data' => [['area_id' => 9, 'area_name' => 'Banani DOHS']]]], 200),
    ]);
}

it('issues a token then creates a Pathao order from the location meta', function () {
    fakeToken();

    $shipment = pathaoShipment(['recipient_city' => 1, 'recipient_zone' => 5, 'recipient_area' => 9]);

    $result = pathao()->createConsignment($shipment);

    expect($result['consignment_id'])->toBe('DA-90210')
        ->and($result['status'])->toBe('Pending');

    Http::assertSent(fn ($r) => Str::contains($r->url(), 'courier-api-sandbox.pathao.com')
        && Str::endsWith($r->url(), '/issue-token'));

    Http::assertSent(function ($r) use ($shipment) {
        return Str::endsWith($r->url(), '/aladdin/api/v1/orders')
            && $r->hasHeader('Authorization', 'Bearer ACCESS-TOKEN')
            && $r['store_id'] === 77
            && $r['recipient_city'] === 1
            && $r['recipient_zone'] === 5
            && $r['recipient_area'] === 9
            && $r['amount_to_collect'] === 2000
            && $r['merchant_order_id'] === $shipment->order->order_no;
    });
});

it('caches the access token across calls (issues it only once)', function () {
    fakeToken();

    $courier = pathao();
    $courier->cities();
    $courier->cities();

    $issued = collect(Http::recorded())
        ->filter(fn ($pair) => Str::endsWith($pair[0]->url(), '/issue-token'))
        ->count();

    expect($issued)->toBe(1);
});

it('walks the city → zone → area cascade', function () {
    fakeToken();
    $courier = pathao();

    expect($courier->cities())->toBe([['id' => 1, 'name' => 'Dhaka']])
        ->and($courier->zones(1))->toBe([['id' => 5, 'name' => 'Banani']])
        ->and($courier->areas(5))->toBe([['id' => 9, 'name' => 'Banani DOHS']]);
});

it('refuses to book when the location is not fully selected', function () {
    fakeToken();
    $shipment = pathaoShipment(['recipient_city' => 1]); // no zone/area

    expect(fn () => pathao()->createConsignment($shipment))->toThrow(RuntimeException::class);
});

it('reads order status from the info endpoint', function () {
    Http::fake([
        '*/issue-token' => Http::response(['access_token' => 'T', 'expires_in' => 3600], 200),
        '*/info' => Http::response(['data' => ['order_status' => 'Delivered']], 200),
    ]);

    expect(pathao()->getStatus('DA-90210'))->toBe('Delivered');
});
