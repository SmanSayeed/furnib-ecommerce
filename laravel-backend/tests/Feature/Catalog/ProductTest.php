<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\ProductService;
use Illuminate\Database\QueryException;

it('stores price and discount as minor units from display amounts', function () {
    $product = Product::factory()->create(['price' => 5000.00, 'discount_price' => 4200.50]);

    expect($product->price->toMinor())->toBe(500000)
        ->and($product->discount_price->toMinor())->toBe(420050);
});

it('reports out of stock when stock status is disabled despite a positive amount', function () {
    $product = Product::factory()->make(['stock_status' => false, 'stock_amount' => 10]);

    expect($product->isInStock())->toBeFalse();
});

it('reports in stock with a positive amount and enabled status', function () {
    $product = Product::factory()->make(['stock_status' => true, 'stock_amount' => 3]);

    expect($product->isInStock())->toBeTrue();
});

it('falls back to the main image for the social thumbnail', function () {
    $product = Product::factory()->make([
        'social_thumbnail_image' => null,
        'main_image' => 'products/main.webp',
    ]);

    expect($product->resolvedSocialThumbnail())->toBe('products/main.webp');
});

it('rejects a duplicate sku', function () {
    Product::factory()->create(['sku' => 'FNB-DUP']);
    Product::factory()->create(['sku' => 'FNB-DUP']);
})->throws(QueryException::class);

it('allows up to six gallery images and rejects the seventh', function () {
    $product = Product::factory()->create();
    $service = app(ProductService::class);

    foreach (range(1, 6) as $i) {
        $service->addImage($product, ['path' => "products/g{$i}.webp", 'position' => $i]);
    }

    expect(ProductImage::where('product_id', $product->id)->count())->toBe(6);

    $service->addImage($product, ['path' => 'products/g7.webp', 'position' => 7]);
})->throws(DomainException::class);
