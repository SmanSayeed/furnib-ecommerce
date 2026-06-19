<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function productManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // has catalog.manage

    return $user;
}

it('blocks users without catalog.manage from creating products', function () {
    $user = User::factory()->create();
    $user->assignRole('marketer'); // only marketing.manage

    actingAs($user)
        ->post('/admin/catalog/products', ['title' => 'Sneaky'])
        ->assertForbidden();
});

it('lists products for staff with catalog.view', function () {
    Product::factory()->create(['title' => 'Oak Chair']);

    actingAs(productManager())
        ->get('/admin/catalog/products')
        ->assertOk();
});

it('creates a product with main image and gallery', function () {
    Storage::fake('public');
    $category = Category::factory()->create();

    actingAs(productManager())
        ->post('/admin/catalog/products', [
            'category_id' => $category->id,
            'title' => 'Walnut Table',
            'price' => '1500.50',
            'product_status' => 'published',
            'main_image' => UploadedFile::fake()->image('m.jpg', 800, 800),
            'gallery_new' => [
                UploadedFile::fake()->image('g1.jpg', 600, 600),
                UploadedFile::fake()->image('g2.jpg', 600, 600),
            ],
            'gallery_layout' => json_encode([
                ['type' => 'new', 'index' => 0],
                ['type' => 'new', 'index' => 1],
            ]),
        ])
        ->assertRedirect(route('admin.products.index'));

    $product = Product::query()->where('title', 'Walnut Table')->firstOrFail();

    expect($product->slug)->toBe('walnut-table');
    expect($product->sku)->not->toBeNull();
    expect($product->price->toMinor())->toBe(150050); // 1500.50 taka -> paisa
    expect($product->main_image)->not->toBeNull();
    expect($product->images()->count())->toBe(2);
    Storage::disk('public')->assertExists($product->main_image);
});

it('rejects an svg main image', function () {
    $category = Category::factory()->create();

    actingAs(productManager())
        ->post('/admin/catalog/products', [
            'category_id' => $category->id,
            'title' => 'Bad',
            'price' => '10',
            'product_status' => 'draft',
            'main_image' => UploadedFile::fake()->create('x.svg', 8, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('main_image');
});

it('rejects more than six gallery images', function () {
    $category = Category::factory()->create();

    $files = [];
    for ($i = 0; $i < 7; $i++) {
        $files[] = UploadedFile::fake()->image("g{$i}.jpg", 100, 100);
    }

    actingAs(productManager())
        ->post('/admin/catalog/products', [
            'category_id' => $category->id,
            'title' => 'Too many',
            'price' => '10',
            'product_status' => 'draft',
            'gallery_new' => $files,
        ])
        ->assertSessionHasErrors('gallery_new');
});

it('updates a product: reorders a kept image, drops one, and appends a new one', function () {
    Storage::fake('public');
    $product = Product::factory()->create(['title' => 'Old']);
    $a = ProductImage::factory()->for($product)->create(['position' => 0]);
    $b = ProductImage::factory()->for($product)->create(['position' => 1]);

    actingAs(productManager())
        ->put("/admin/catalog/products/{$product->id}", [
            'category_id' => $product->category_id,
            'title' => 'New name',
            'price' => '200',
            'product_status' => 'published',
            'gallery_new' => [UploadedFile::fake()->image('n.jpg', 300, 300)],
            'gallery_layout' => json_encode([
                ['type' => 'existing', 'id' => $b->id], // b moves to front
                ['type' => 'new', 'index' => 0],         // append one new
                // a is omitted -> deleted
            ]),
        ])
        ->assertRedirect(route('admin.products.index'));

    expect($product->refresh()->title)->toBe('New name');
    expect(ProductImage::query()->find($a->id))->toBeNull();
    expect($b->refresh()->position)->toBe(0);
    expect($product->images()->count())->toBe(2);
});

it('soft-deletes a product to the recycle bin', function () {
    $product = Product::factory()->create();

    actingAs(productManager())
        ->delete("/admin/catalog/products/{$product->id}")
        ->assertRedirect(route('admin.products.index'));

    expect(Product::query()->find($product->id))->toBeNull();
    expect(Product::withTrashed()->find($product->id))->not->toBeNull();
});

it('restores a trashed product', function () {
    $product = Product::factory()->create();
    $product->delete();

    actingAs(productManager())
        ->post("/admin/catalog/products/{$product->id}/restore")
        ->assertRedirect(route('admin.products.trashed'));

    expect(Product::query()->find($product->id))->not->toBeNull();
});

it('permanently deletes a trashed product and its gallery rows', function () {
    Storage::fake('public');
    $product = Product::factory()->create();
    $image = ProductImage::factory()->for($product)->create();
    $product->delete();

    actingAs(productManager())
        ->delete("/admin/catalog/products/{$product->id}/force")
        ->assertRedirect(route('admin.products.trashed'));

    expect(Product::withTrashed()->find($product->id))->toBeNull();
    expect(ProductImage::query()->find($image->id))->toBeNull();
});
