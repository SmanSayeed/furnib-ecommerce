<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Order;
use App\Services\Settings\SettingsService;
use App\Support\Money;
use App\Support\Payments\SslCommerzGateway;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    cache()->flush();
    $s = app(SettingsService::class);
    $s->set('sslcommerz', 'sandbox_store_id', 'SBOXID', false);
    $s->set('sslcommerz', 'sandbox_store_passwd', 'sbox-pass', true);
    $s->set('sslcommerz', 'live_store_id', 'LIVEID', false);
    $s->set('sslcommerz', 'live_store_passwd', 'live-pass', true);
    cache()->flush();
});

function sslOrder(): Order
{
    $customer = Customer::factory()->create(['name' => 'Karim', 'mobile' => '+8801712345678']);

    return Order::factory()->create(['customer_id' => $customer->id]);
}

it('uses the sandbox credentials and URL in sandbox mode', function () {
    app(SettingsService::class)->set('sslcommerz', 'sandbox', true);
    cache()->flush();
    Http::fake(['*gwprocess*' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://gw'], 200)]);

    app(SslCommerzGateway::class)->initSession(sslOrder(), Money::fromMinor(100000), 'T-SBOX');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sandbox.sslcommerz.com')
        && $request['store_id'] === 'SBOXID');
});

it('uses the live credentials and URL in live mode', function () {
    app(SettingsService::class)->set('sslcommerz', 'sandbox', false);
    cache()->flush();
    Http::fake(['*gwprocess*' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://gw'], 200)]);

    app(SslCommerzGateway::class)->initSession(sslOrder(), Money::fromMinor(100000), 'T-LIVE');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'securepay.sslcommerz.com')
        && $request['store_id'] === 'LIVEID');
});

it('keeps both credential sets in the database side by side', function () {
    $s = app(SettingsService::class);

    expect($s->get('sslcommerz', 'sandbox_store_id'))->toBe('SBOXID')
        ->and($s->get('sslcommerz', 'sandbox_store_passwd'))->toBe('sbox-pass')
        ->and($s->get('sslcommerz', 'live_store_id'))->toBe('LIVEID')
        ->and($s->get('sslcommerz', 'live_store_passwd'))->toBe('live-pass');
});
