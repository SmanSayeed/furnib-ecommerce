<?php

declare(strict_types=1);

use App\Models\Product;
use App\Services\Marketing\ProductFeed;
use App\Services\Settings\SettingsService;
use App\Support\Marketing\FeedAccess;

/** Enable the feed and return [slug, username, password] for a Basic-auth request. */
function enableFeed(): array
{
    $settings = app(SettingsService::class);
    $settings->set(FeedAccess::GROUP, 'feed_slug', 'test-feed-slug');
    $settings->set(FeedAccess::GROUP, 'feed_username', 'furnib-feed');
    $settings->set(FeedAccess::GROUP, 'feed_password', 'secret-pass', isSecret: true);
    $settings->set(FeedAccess::GROUP, 'feed_enabled', true);

    return ['test-feed-slug', 'furnib-feed', 'secret-pass'];
}

it('emits a CSV header row with the expected schema when authenticated', function () {
    [$slug, $user, $pass] = enableFeed();

    $response = $this->withHeaders(['Authorization' => 'Basic '.base64_encode("{$user}:{$pass}")])
        ->get("/feed/{$slug}/products.csv")
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $firstLine = strtok($response->getContent(), "\n");
    expect($firstLine)->toContain('id,title,description,availability,condition,price,sale_price,link,image_link,additional_image_link,brand');
});

it('returns 404 when the feed is disabled', function () {
    [$slug] = enableFeed();
    app(SettingsService::class)->set(FeedAccess::GROUP, 'feed_enabled', false);

    $this->get("/feed/{$slug}/products.csv")->assertNotFound();
});

it('returns 404 for an unknown feed slug', function () {
    enableFeed();

    $this->get('/feed/wrong-slug/products.csv')->assertNotFound();
});

it('challenges with 401 when Basic credentials are missing', function () {
    [$slug] = enableFeed();

    $this->get("/feed/{$slug}/products.csv")
        ->assertStatus(401)
        ->assertHeader('WWW-Authenticate', 'Basic realm="Furnib product feed"');
});

it('rejects wrong Basic credentials', function () {
    [$slug, $user] = enableFeed();

    $this->withHeaders(['Authorization' => 'Basic '.base64_encode("{$user}:wrong")])
        ->get("/feed/{$slug}/products.csv")
        ->assertStatus(401);
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

it('emits the extended Meta fields', function () {
    $category = App\Models\Category::factory()->create(['title' => 'Living Room']);
    Product::factory()->create([
        'title' => 'Teak Sofa', 'sku' => 'SOFA-1', 'slug' => 'teak-sofa',
        'category_id' => $category->id,
        'product_status' => 'published', 'stock_status' => true, 'stock_amount' => 7,
    ]);

    $rows = app(ProductFeed::class)->rows();
    $row = collect($rows)->firstWhere('id', 'SOFA-1');

    expect($row['product_type'])->toBe('Living Room')                 // category breadcrumb
        ->and($row['item_group_id'])->toBe('SOFA-1')                  // equals id (no variants)
        ->and($row['quantity_to_sell_on_facebook'])->toBe('7');       // real stock
});

it('filters the export by category', function () {
    $living = App\Models\Category::factory()->create(['title' => 'Living Room']);
    $office = App\Models\Category::factory()->create(['title' => 'Office']);
    Product::factory()->create(['title' => 'Sofa', 'slug' => 'sofa', 'category_id' => $living->id, 'product_status' => 'published']);
    Product::factory()->create(['title' => 'Desk', 'slug' => 'desk', 'category_id' => $office->id, 'product_status' => 'published']);

    $titles = array_column(app(ProductFeed::class)->rows([$living->id]), 'title');

    expect($titles)->toContain('Sofa')->not->toContain('Desk');
});
