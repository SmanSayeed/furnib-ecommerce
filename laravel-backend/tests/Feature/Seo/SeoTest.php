<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Services\Seo\JsonLd;
use App\Services\Seo\OgImageResolver;

it('lists only published products and active categories in the sitemap', function () {
    Product::factory()->create(['slug' => 'published-sofa', 'product_status' => 'published']);
    Product::factory()->create(['slug' => 'draft-chair', 'product_status' => 'draft']);
    Category::factory()->create(['slug' => 'living-room', 'status' => true]);
    Category::factory()->create(['slug' => 'hidden-cat', 'status' => false]);

    $response = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml');

    $body = $response->getContent();
    expect($body)->toContain('/products/published-sofa')
        ->and($body)->toContain('/category/living-room')
        ->and($body)->not->toContain('draft-chair')
        ->and($body)->not->toContain('hidden-cat');
});

it('serves a robots.txt that points to the sitemap and blocks admin', function () {
    $response = $this->get('/robots.txt')->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/plain');

    expect($response->getContent())
        ->toContain('User-agent: *')
        ->toContain('Disallow: /admin')
        ->toContain('Sitemap:')
        ->toContain('/sitemap.xml');
});

it('builds Product JSON-LD with offer + availability', function () {
    $inStock = Product::factory()->create([
        'title' => 'Teak Sofa', 'sku' => 'SOFA-1', 'price' => 25000,
        'stock_status' => true, 'stock_amount' => 5,
    ]);

    $schema = app(JsonLd::class)->product($inStock, 'https://shop/products/teak-sofa', 'https://shop/img.jpg');

    expect($schema['@type'])->toBe('Product')
        ->and($schema['name'])->toBe('Teak Sofa')
        ->and($schema['sku'])->toBe('SOFA-1')
        ->and($schema['offers']['priceCurrency'])->toBe('BDT')
        ->and($schema['offers']['price'])->toBe('25000.00')
        ->and($schema['offers']['availability'])->toBe('https://schema.org/InStock');
});

it('marks an out-of-stock product as OutOfStock in JSON-LD', function () {
    $product = Product::factory()->create(['stock_status' => false, 'stock_amount' => 0]);

    expect(app(JsonLd::class)->product($product)['offers']['availability'])
        ->toBe('https://schema.org/OutOfStock');
});

it('builds an ordered BreadcrumbList', function () {
    $schema = app(JsonLd::class)->breadcrumb([
        ['name' => 'Home', 'url' => 'https://shop'],
        ['name' => 'Sofas', 'url' => 'https://shop/category/sofas'],
    ]);

    expect($schema['@type'])->toBe('BreadcrumbList')
        ->and($schema['itemListElement'][0]['position'])->toBe(1)
        ->and($schema['itemListElement'][1]['name'])->toBe('Sofas')
        ->and($schema['itemListElement'][1]['position'])->toBe(2);
});

it('resolves OG image with a fallback chain', function () {
    $resolver = app(OgImageResolver::class);

    $withOg = Product::factory()->create(['og_image' => 'og.jpg', 'main_image' => 'main.jpg']);
    expect($resolver->forProduct($withOg))->toBe('og.jpg');

    $withMainOnly = Product::factory()->create(['og_image' => null, 'social_thumbnail_image' => null, 'main_image' => 'main.jpg']);
    expect($resolver->forProduct($withMainOnly))->toBe('main.jpg');

    $category = Category::factory()->create(['og_image' => null, 'header_image' => 'header.jpg']);
    expect($resolver->forCategory($category))->toBe('header.jpg');
});
