<?php

declare(strict_types=1);

use App\Models\Product;
use App\Services\Marketing\ProductFeed;

it('emits a CSV header row with the expected schema', function () {
    $response = $this->get('/feed/products.csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $firstLine = strtok($response->getContent(), "\n");
    expect($firstLine)->toContain('id,title,description,availability,condition,price,sale_price,link,image_link,additional_image_link,brand');
});

it('includes published products and reflects stock availability', function () {
    Product::factory()->create([
        'title' => 'Teak Sofa', 'sku' => 'SOFA-1', 'slug' => 'teak-sofa',
        'price' => 25000, 'discount_price' => 19999, 'product_status' => 'published',
        'stock_status' => true, 'stock_amount' => 5,
    ]);
    Product::factory()->create([
        'title' => 'Oak Stool', 'sku' => 'STOOL-9', 'slug' => 'oak-stool',
        'product_status' => 'published', 'stock_status' => false, 'stock_amount' => 0,
    ]);

    $csv = app(ProductFeed::class)->csv();

    expect($csv)->toContain('SOFA-1')
        ->toContain('Teak Sofa')
        ->toContain('25000.00 BDT')      // regular price
        ->toContain('19999.00 BDT')      // sale_price (discount)
        ->toContain('/product/teak-sofa') // real landing page, not /products/ (404)
        ->toContain('in stock')
        ->toContain('out of stock');
});

it('excludes unpublished products from the feed', function () {
    Product::factory()->create(['title' => 'Live Bed', 'slug' => 'live-bed', 'product_status' => 'published']);
    Product::factory()->create(['title' => 'Secret Draft', 'slug' => 'secret-draft', 'product_status' => 'draft']);

    $rows = app(ProductFeed::class)->rows();
    $titles = array_column($rows, 'title');

    expect($titles)->toContain('Live Bed')
        ->and($titles)->not->toContain('Secret Draft');
});
