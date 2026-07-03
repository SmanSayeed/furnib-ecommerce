<?php

declare(strict_types=1);

use App\Models\Product;

it('returns published products matching the query', function () {
    Product::factory()->create(['title' => 'Oak Dining Table', 'product_status' => 'published']);
    Product::factory()->create(['title' => 'Steel Office Chair', 'product_status' => 'published']);

    $response = $this->getJson('/api/v1/products?q=oak')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.title'))->toBe('Oak Dining Table');
});

it('never returns unpublished products', function () {
    Product::factory()->draft()->create(['title' => 'Secret Draft Sofa']);

    $this->getJson('/api/v1/products?q=sofa')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns nothing for a too-short query', function () {
    Product::factory()->create(['title' => 'Aaa Bench', 'product_status' => 'published']);

    $this->getJson('/api/v1/products?q=a')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('caps the result count at the requested limit', function () {
    Product::factory()->count(10)->create(['product_status' => 'published']);
    // All share the "FNB-" sku prefix the factory generates.
    $response = $this->getJson('/api/v1/products?q=FNB&limit=3')->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

it('treats LIKE wildcards in the query as literal characters', function () {
    Product::factory()->create(['title' => 'Plain Shelf', 'product_status' => 'published']);

    // A bare "%" must not match everything.
    $this->getJson('/api/v1/products?q=%25%25')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
