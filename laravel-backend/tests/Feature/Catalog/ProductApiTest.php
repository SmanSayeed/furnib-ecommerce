<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;

it('fetches a published product by slug with price and availability', function () {
    Product::factory()->create(['slug' => 'lovinna-chair', 'price' => 5000.00]);

    $this->getJson('/api/v1/products/lovinna-chair')
        ->assertOk()
        ->assertJsonPath('data.slug', 'lovinna-chair')
        ->assertJsonPath('data.price.minor', 500000)
        ->assertJsonPath('data.in_stock', true);
});

it('returns 404 for a draft product', function () {
    Product::factory()->draft()->create(['slug' => 'hidden-prod']);

    $this->getJson('/api/v1/products/hidden-prod')
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');
});

it('lists a category with only its published products, paginated', function () {
    $category = Category::factory()->create(['slug' => 'chairs', 'status' => true]);
    Product::factory()->count(3)->create(['category_id' => $category->id, 'product_status' => 'published']);
    Product::factory()->draft()->create(['category_id' => $category->id]);

    $response = $this->getJson('/api/v1/categories/chairs')->assertOk();

    expect($response->json('data.slug'))->toBe('chairs')
        ->and($response->json('products'))->toHaveCount(3)
        ->and($response->json('meta.total'))->toBe(3);
});
