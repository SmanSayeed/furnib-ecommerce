<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\User;
use App\Services\Marketing\ProductFeed;
use Database\Seeders\PermissionRoleSeeder;

/**
 * The public API, the product feed and the write endpoints must all agree with
 * Product::effectivePrice(). If any of them advertises a discount the server
 * would not honour, we recreate the original bug in reverse: the page shows one
 * price and the order charges another.
 */
beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager'); // catalog.view + catalog.manage
});

it('exposes an effective discount on the public product API', function () {
    $product = Product::factory()->create([
        'price' => 1000, 'discount_price' => 800, 'product_status' => 'published',
    ]);

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.discount_price.minor', 80000)
        ->assertJsonPath('data.price.minor', 100000);
});

it('hides a discount that is not below the price', function () {
    // A legacy row the (previously unguarded) API update endpoint could create.
    // The storefront does `discount_price ?? price`, so exposing this would make
    // the page advertise ৳1,200 as a "discount" on a ৳1,000 product.
    $product = Product::factory()->create([
        'price' => 1000, 'discount_price' => 1200, 'product_status' => 'published',
    ]);

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.discount_price', null);
});

it('omits an ineffective sale price from the product feed', function () {
    Product::factory()->create([
        'sku' => 'FEED-BAD', 'price' => 1000, 'discount_price' => 1000, 'product_status' => 'published',
    ]);

    $row = collect(app(ProductFeed::class)->rows())->firstWhere('id', 'FEED-BAD');

    expect($row['sale_price'])->toBe('')
        ->and($row['price'])->toBe('1000.00 BDT');
});

it('emits an effective sale price in the product feed', function () {
    Product::factory()->create([
        'sku' => 'FEED-OK', 'price' => 1000, 'discount_price' => 800, 'product_status' => 'published',
    ]);

    $row = collect(app(ProductFeed::class)->rows())->firstWhere('id', 'FEED-OK');

    expect($row['sale_price'])->toBe('800.00 BDT');
});

it('rejects an API update whose discount is not below the price', function () {
    $product = Product::factory()->create(['price' => 1000, 'discount_price' => null]);

    $this->actingAs($this->manager)
        ->putJson("/admin/products/{$product->id}", ['discount_price' => 1000])
        ->assertStatus(422)
        ->assertJsonValidationErrors('discount_price');
});

it('rejects an API update whose discount is above the price', function () {
    $product = Product::factory()->create(['price' => 1000, 'discount_price' => null]);

    $this->actingAs($this->manager)
        ->putJson("/admin/products/{$product->id}", ['discount_price' => 1200])
        ->assertStatus(422)
        ->assertJsonValidationErrors('discount_price');
});

it('accepts an API update with a valid discount when the price is not resent', function () {
    // Partial update: only discount_price is sent. `lt:price` must compare
    // against the product's STORED price, not a missing field.
    $product = Product::factory()->create(['price' => 1000, 'discount_price' => null]);

    $this->actingAs($this->manager)
        ->putJson("/admin/products/{$product->id}", ['discount_price' => 800])
        ->assertOk();

    expect($product->refresh()->discount_price?->toMinor())->toBe(80000);
});
