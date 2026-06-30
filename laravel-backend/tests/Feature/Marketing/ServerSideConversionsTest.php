<?php

declare(strict_types=1);

use App\Actions\Marketing\ConfirmOrderPurchase;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Support\Capi\ConversionApi;
use App\Support\Capi\FakeConversionApi;
use App\Support\Ga4\FakeMeasurementProtocol;
use App\Support\Ga4\MeasurementProtocol;
use App\Support\Tiktok\FakeEventsApi;
use App\Support\Tiktok\TiktokUserData;
use App\Support\Tiktok\EventsApi;

beforeEach(function () {
    cache()->flush();
    $this->meta = new FakeConversionApi;
    $this->tiktok = new FakeEventsApi;
    $this->ga4 = new FakeMeasurementProtocol;
    $this->app->instance(ConversionApi::class, $this->meta);
    $this->app->instance(EventsApi::class, $this->tiktok);
    $this->app->instance(MeasurementProtocol::class, $this->ga4);
});

it('fires Meta, TikTok and GA4 purchases together when an order is confirmed', function () {
    $order = Order::factory()->create([
        'status' => 'pending',
        'total' => 5000,
        'ttp' => 'ttp.abc',
        'ttclid' => 'tt.click',
        'ga_client_id' => '111.222',
    ]);

    app(ConfirmOrderPurchase::class)->handle($order);

    // Meta
    expect($this->meta->ofType('Purchase'))->toHaveCount(1)
        ->and($this->meta->ofType('Purchase')[0]->eventId)->toBe('purchase.'.$order->order_no);

    // TikTok — CompletePayment, same dedup id, attribution cookies attached.
    $tt = $this->tiktok->ofType('CompletePayment');
    expect($tt)->toHaveCount(1)
        ->and($tt[0]->eventId)->toBe('purchase.'.$order->order_no);
    $ttUser = $tt[0]->userData->toArray();
    expect($ttUser['ttp'])->toBe('ttp.abc')
        ->and($ttUser['ttclid'])->toBe('tt.click');

    // GA4 — purchase with the captured client id + transaction id.
    $ga = $this->ga4->ofType('purchase');
    expect($ga)->toHaveCount(1)
        ->and($ga[0]->clientId)->toBe('111.222')
        ->and($ga[0]->params['transaction_id'])->toBe($order->order_no)
        ->and($ga[0]->params['currency'])->toBe('BDT');
});

it('falls back to a per-order GA4 client id when none was captured', function () {
    $order = Order::factory()->create(['status' => 'pending', 'total' => 5000, 'ga_client_id' => null]);

    app(ConfirmOrderPurchase::class)->handle($order);

    $ga = $this->ga4->ofType('purchase');
    expect($ga)->toHaveCount(1)
        ->and($ga[0]->clientId)->toBe('srv.'.$order->order_no);
});

it('is idempotent across all platforms — a second confirm never refires', function () {
    $order = Order::factory()->create(['status' => 'pending', 'total' => 5000]);
    $action = app(ConfirmOrderPurchase::class);

    expect($action->handle($order))->toBeTrue()
        ->and($action->handle($order->refresh()))->toBeFalse()
        ->and($this->meta->ofType('Purchase'))->toHaveCount(1)
        ->and($this->tiktok->ofType('CompletePayment'))->toHaveCount(1)
        ->and($this->ga4->ofType('purchase'))->toHaveCount(1);
});

it('forwards a funnel event to BOTH Meta and TikTok via /collect', function () {
    $category = Category::factory()->create(['title' => 'Lamps']);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'sku' => 'SKU-9', 'product_status' => 'published',
        'price' => 1000, 'stock_amount' => 5, 'stock_status' => true,
    ]);

    $this->postJson('/api/v1/collect', [
        'event' => 'ViewContent',
        'event_id' => 'view.SKU-9.1',
        'sku' => 'SKU-9',
        'qty' => 1,
        'ttp' => 'ttp.y',
        'ttclid' => 'cl.y',
    ])->assertOk();

    expect($this->meta->ofType('ViewContent'))->toHaveCount(1);

    $tt = $this->tiktok->ofType('ViewContent');
    expect($tt)->toHaveCount(1)
        ->and($tt[0]->eventId)->toBe('view.SKU-9.1')
        ->and($tt[0]->userData->toArray()['ttp'])->toBe('ttp.y');
});

it('maps a Lead funnel event to the TikTok Contact event', function () {
    $product = Product::factory()->create([
        'sku' => 'SKU-L', 'product_status' => 'published',
        'price' => 1000, 'stock_amount' => 5, 'stock_status' => true,
    ]);

    $this->postJson('/api/v1/collect', [
        'event' => 'Lead',
        'event_id' => 'lead.SKU-L.1',
        'sku' => 'SKU-L',
        'qty' => 1,
    ])->assertOk();

    expect($this->tiktok->ofType('Contact'))->toHaveCount(1);
});

it('persists ttp/ttclid/ga_client_id captured at checkout', function () {
    $product = Product::factory()->create([
        'product_status' => 'published',
        'price' => 1000, 'stock_amount' => 5, 'stock_status' => true,
    ]);

    $this->withHeaders([
        'X-Ttp' => 'ttp.checkout',
        'X-Ttclid' => 'tt.checkout',
        'X-Ga-Client-Id' => '999.888',
    ])->postJson('/api/v1/orders', [
        'items' => [['product_id' => $product->id, 'qty' => 1]],
        'customer' => ['name' => 'Karim', 'mobile' => '01712345678'],
        'address' => 'House 1, Road 2, Dhaka',
    ])->assertCreated();

    $order = Order::query()->latest('id')->firstOrFail();
    expect($order->ttp)->toBe('ttp.checkout')
        ->and($order->ttclid)->toBe('tt.checkout')
        ->and($order->ga_client_id)->toBe('999.888');
});

it('hashes TikTok PII with a leading + on the phone (E.164)', function () {
    $user = new TiktokUserData(email: 'Buyer@Example.com', phone: '01712345678');
    $data = $user->toArray();

    expect($data['email'])->toBe(hash('sha256', 'buyer@example.com'))
        ->and($data['phone'])->toBe(hash('sha256', '+8801712345678'));
});
