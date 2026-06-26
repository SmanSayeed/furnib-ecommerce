<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function catalogViewer(): User
{
    $user = User::factory()->create();
    $user->assignRole('sub-admin'); // catalog.view + orders.view

    return $user;
}

it('denies the product list to users without catalog.view', function () {
    actingAs(User::factory()->create())
        ->get('/admin/catalog/products')
        ->assertForbidden();
});

it('sorts products by price descending', function () {
    $cheap = Product::factory()->create(['price' => 100]);
    $pricey = Product::factory()->create(['price' => 9000]);

    actingAs(catalogViewer())
        ->get('/admin/catalog/products?sort=price&dir=desc')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/products/index')
            ->where('products.0.id', $pricey->id)
            ->where('products.1.id', $cheap->id));
});

it('limits products to this month with the this_month preset', function () {
    Product::factory()->create(['created_at' => Carbon::now()]);
    Product::factory()->create(['created_at' => Carbon::now()->subMonthsNoOverflow(2)]);

    actingAs(catalogViewer())
        ->get('/admin/catalog/products?range=this_month')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products', 1));
});

it('still filters products by status and search alongside sort', function () {
    Product::factory()->create(['title' => 'Teak Dining Table', 'product_status' => 'published']);
    Product::factory()->draft()->create(['title' => 'Teak Wardrobe']);

    actingAs(catalogViewer())
        ->get('/admin/catalog/products?search=Teak&status=published&sort=title&dir=asc')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.title', 'Teak Dining Table'));
});
