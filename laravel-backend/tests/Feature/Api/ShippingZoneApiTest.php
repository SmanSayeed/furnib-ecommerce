<?php

declare(strict_types=1);

use App\Models\ShippingZone;

it('returns only active shipping zones ordered for the storefront', function () {
    ShippingZone::factory()->create(['name' => 'Inside Dhaka', 'cost' => 80, 'status' => true, 'position_order' => 1]);
    ShippingZone::factory()->create(['name' => 'Outside Dhaka', 'cost' => 150, 'status' => true, 'position_order' => 2]);
    ShippingZone::factory()->inactive()->create(['name' => 'Hidden Zone']);

    $response = $this->getJson('/api/v1/shipping-zones');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect($response->json('data.0.name'))->toBe('Inside Dhaka');
    expect($response->json('data.0.cost.minor'))->toBe(8000);
    expect(collect($response->json('data'))->pluck('name'))->not->toContain('Hidden Zone');
});
