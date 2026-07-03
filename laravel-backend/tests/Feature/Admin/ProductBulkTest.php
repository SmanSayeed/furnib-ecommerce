<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function bulkCatalogManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // catalog.manage

    return $user;
}

function bulkCatalogViewer(): User
{
    $user = User::factory()->create();
    $user->assignRole('marketer'); // no catalog permission at all

    return $user;
}

it('bulk-sets the status on explicitly selected products', function () {
    $products = Product::factory()->count(3)->create(['product_status' => 'published']);

    actingAs(bulkCatalogManager())
        ->post('/admin/catalog/products/bulk', [
            'action' => 'status',
            'ids' => $products->pluck('id')->all(),
            'product_status' => 'disabled',
        ])->assertRedirect();

    expect(Product::query()->where('product_status', 'disabled')->count())->toBe(3);
});

it('bulk-assigns a category', function () {
    $target = Category::factory()->create();
    $products = Product::factory()->count(2)->create();

    actingAs(bulkCatalogManager())
        ->post('/admin/catalog/products/bulk', [
            'action' => 'category',
            'ids' => $products->pluck('id')->all(),
            'category_id' => $target->id,
        ])->assertRedirect();

    expect(Product::query()->where('category_id', $target->id)->count())->toBe(2);
});

it('bulk-enables partial advance payment with its config', function () {
    $product = Product::factory()->create(['is_advance_payment' => false]);

    actingAs(bulkCatalogManager())
        ->post('/admin/catalog/products/bulk', [
            'action' => 'advance',
            'ids' => [$product->id],
            'is_advance_payment' => true,
            'advance_payment_type' => 'partial',
            'partial_amount_type' => 'percentage',
            'partial_amount' => 30,
        ])->assertRedirect();

    $product->refresh();
    expect($product->is_advance_payment)->toBeTrue()
        ->and($product->advance_payment_type)->toBe('partial')
        ->and($product->partial_amount_type)->toBe('percentage')
        ->and($product->partial_amount)->toBe(30);
});

it('bulk-disabling advance clears the partial config', function () {
    $product = Product::factory()->create([
        'is_advance_payment' => true,
        'advance_payment_type' => 'partial',
        'partial_amount_type' => 'amount',
        'partial_amount' => 500,
    ]);

    actingAs(bulkCatalogManager())
        ->post('/admin/catalog/products/bulk', [
            'action' => 'advance',
            'ids' => [$product->id],
            'is_advance_payment' => false,
        ])->assertRedirect();

    $product->refresh();
    expect($product->is_advance_payment)->toBeFalse()
        ->and($product->partial_amount_type)->toBeNull()
        ->and($product->partial_amount)->toBeNull();
});

it('applies to every product matching the current filters when all_matching is set', function () {
    Product::factory()->count(2)->draft()->create();
    $published = Product::factory()->count(3)->create(['product_status' => 'published']);

    actingAs(bulkCatalogManager())
        ->post('/admin/catalog/products/bulk', [
            'action' => 'status',
            'all_matching' => true,
            'filters' => ['status' => 'draft'],
            'product_status' => 'disabled',
        ])->assertRedirect();

    // Only the drafts (matching the filter) changed; published stayed untouched.
    expect(Product::query()->where('product_status', 'disabled')->count())->toBe(2)
        ->and(Product::query()->whereIn('id', $published->pluck('id'))->where('product_status', 'published')->count())->toBe(3);
});

it('records a single audit entry for the bulk edit', function () {
    $products = Product::factory()->count(4)->create();

    actingAs(bulkCatalogManager())
        ->post('/admin/catalog/products/bulk', [
            'action' => 'status',
            'ids' => $products->pluck('id')->all(),
            'product_status' => 'draft',
        ])->assertRedirect();

    $log = Activity::query()->where('log_name', 'Product')->where('description', 'bulk_update')->first();
    expect($log)->not->toBeNull()
        ->and($log->properties['count'])->toBe(4);
});

it('rejects an invalid status value', function () {
    $product = Product::factory()->create();

    actingAs(bulkCatalogManager())
        ->post('/admin/catalog/products/bulk', [
            'action' => 'status',
            'ids' => [$product->id],
            'product_status' => 'archived',
        ])->assertSessionHasErrors('product_status');
});

it('blocks catalog.view-only staff from bulk editing', function () {
    $product = Product::factory()->create();

    actingAs(bulkCatalogViewer())
        ->post('/admin/catalog/products/bulk', [
            'action' => 'status',
            'ids' => [$product->id],
            'product_status' => 'draft',
        ])->assertForbidden();
});
