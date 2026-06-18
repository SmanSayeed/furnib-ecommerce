<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager'); // has catalog.view + catalog.manage
});

it('creates a product with price stored as minor units', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->manager)
        ->postJson('/admin/products', [
            'category_id' => $category->id,
            'title' => 'Office Chair',
            'price' => 5000.00,
            'product_status' => 'published',
        ])
        ->assertCreated()
        ->assertJsonPath('data.price.minor', 500000);
});

it('forbids creating a product without catalog.manage', function () {
    $category = Category::factory()->create();
    $user = User::factory()->create();
    $user->assignRole('marketer');

    $this->actingAs($user)
        ->postJson('/admin/products', [
            'category_id' => $category->id,
            'title' => 'Nope',
            'price' => 10,
            'product_status' => 'draft',
        ])
        ->assertForbidden();
});

it('soft deletes then restores a product', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->manager)->deleteJson("/admin/products/{$product->id}")->assertNoContent();
    $this->assertSoftDeleted($product);

    $this->actingAs($this->manager)->postJson("/admin/products/{$product->id}/restore")->assertOk();
    $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
});

it('hard deletes a trashed product', function () {
    $product = Product::factory()->create();
    $product->delete();

    $this->actingAs($this->manager)->deleteJson("/admin/products/{$product->id}/force")->assertNoContent();
    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});

it('searches products by sku', function () {
    Product::factory()->create(['sku' => 'FNB-FINDME']);
    Product::factory()->create(['sku' => 'FNB-OTHER']);

    $response = $this->actingAs($this->manager)->getJson('/admin/products?search=FINDME')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.sku'))->toBe('FNB-FINDME');
});

it('filters products by status', function () {
    Product::factory()->count(2)->create(['product_status' => 'published']);
    Product::factory()->draft()->create();

    $response = $this->actingAs($this->manager)->getJson('/admin/products?status=draft')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('exports the filtered products as csv', function () {
    Product::factory()->create(['sku' => 'FNB-CSV']);

    $response = $this->actingAs($this->manager)->get('/admin/products/export')->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->getContent())->toContain('FNB-CSV');
});
