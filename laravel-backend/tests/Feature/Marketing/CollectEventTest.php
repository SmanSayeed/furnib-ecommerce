<?php

declare(strict_types=1);

use App\Models\Product;
use App\Support\Capi\ConversionApi;
use App\Support\Capi\FakeConversionApi;

beforeEach(function () {
    cache()->flush();
    $this->capi = new FakeConversionApi;
    $this->app->instance(ConversionApi::class, $this->capi);
});

it('forwards a ViewContent beacon to the Conversions API with server-derived value', function () {
    Product::factory()->create([
        'sku' => 'CHAIR-7', 'slug' => 'office-chair',
        'price' => 1000, 'discount_price' => null,
        'product_status' => 'published',
    ]);

    $this->postJson('/api/v1/collect', [
        'event' => 'ViewContent',
        'event_id' => 'view.CHAIR-7.123',
        'sku' => 'CHAIR-7',
        'qty' => 2,
        'event_source_url' => 'https://furnib.com/product/office-chair',
        'fbp' => 'fb.1.123.456',
    ])->assertOk()->assertJson(['recorded' => true]);

    $events = $this->capi->ofType('ViewContent');
    expect($events)->toHaveCount(1);

    $payload = $events[0]->toArray();
    expect($payload['event_id'])->toBe('view.CHAIR-7.123')
        ->and($payload['custom_data']['content_ids'])->toBe(['CHAIR-7'])
        ->and($payload['custom_data']['value'])->toBe('2000.00') // 1000 × 2, server-side
        ->and($payload['user_data']['fbp'])->toBe('fb.1.123.456');
});

it('ignores an unknown or unpublished sku without erroring', function () {
    Product::factory()->create(['sku' => 'DRAFT-1', 'slug' => 'draft', 'product_status' => 'draft']);

    $this->postJson('/api/v1/collect', [
        'event' => 'ViewContent', 'event_id' => 'x', 'sku' => 'DRAFT-1',
    ])->assertOk()->assertJson(['recorded' => false]);

    expect($this->capi->events)->toBeEmpty();
});

it('rejects a Purchase event on the public beacon (server owns conversions)', function () {
    $this->postJson('/api/v1/collect', [
        'event' => 'Purchase', 'event_id' => 'purchase.spoof', 'sku' => 'ANY',
    ])->assertStatus(422);

    expect($this->capi->events)->toBeEmpty();
});

it('accepts the Lead and InitiateCheckout funnel events', function () {
    Product::factory()->create(['sku' => 'BED-1', 'slug' => 'bed', 'price' => 5000, 'product_status' => 'published']);

    foreach (['Lead', 'InitiateCheckout'] as $event) {
        $this->postJson('/api/v1/collect', [
            'event' => $event, 'event_id' => $event.'.1', 'sku' => 'BED-1',
        ])->assertOk();
    }

    expect($this->capi->ofType('Lead'))->toHaveCount(1)
        ->and($this->capi->ofType('InitiateCheckout'))->toHaveCount(1);
});
