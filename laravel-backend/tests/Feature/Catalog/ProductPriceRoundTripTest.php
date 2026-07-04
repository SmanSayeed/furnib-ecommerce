<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\PermissionRoleSeeder;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');
});

/**
 * Drives the exact Inertia admin flow the storefront owner used:
 * create with price 10 + full advance, then open the edit form and re-save.
 * Guards against any price inflation or phantom discount across the round trip.
 */
it('keeps price at 10 taka and adds no discount through create + edit', function () {
    $category = Category::factory()->create();

    // 1) CREATE — price 10, full advance, no discount (empty string like the form).
    $this->actingAs($this->manager)
        ->post('/admin/catalog/products', [
            'category_id' => $category->id,
            'title' => 'Test Chair',
            'sku' => 'ST665956',
            'price' => '10',
            'discount_price' => '',
            'is_advance_payment' => '1',
            'advance_payment_type' => 'full',
            'partial_amount_type' => '',
            'partial_amount' => '',
            'stock_amount' => '60',
            'stock_status' => '1',
            'product_status' => 'published',
            'position_order' => '0',
        ])
        ->assertRedirect();

    $product = Product::where('sku', 'ST665956')->firstOrFail();

    expect($product->getRawOriginal('price'))->toBe(1000)          // ৳10 = 1000 paisa
        ->and($product->getRawOriginal('discount_price'))->toBeNull()
        ->and($product->is_advance_payment)->toBeTrue()
        ->and($product->advance_payment_type)->toBe('full');

    // 2) EDIT — the form prefills price from ProductUiController::formData (toDisplay).
    $editData = $this->actingAs($this->manager)
        ->get("/admin/catalog/products/{$product->id}/edit")
        ->assertOk();

    // The Inertia prop the React form binds to must be 10, not 1000 or 2000.
    $prop = $editData->viewData('page')['props']['product'];
    expect($prop['price'])->toBe(10.0)
        ->and($prop['discount_price'])->toBeNull();

    // 3) RE-SAVE — submit exactly what the prefilled form would send back.
    $this->actingAs($this->manager)
        ->put("/admin/catalog/products/{$product->id}", [
            'category_id' => $category->id,
            'title' => 'Test Chair',
            'sku' => 'ST665956',
            'price' => (string) $prop['price'],
            'discount_price' => '',
            'is_advance_payment' => '1',
            'advance_payment_type' => 'full',
            'partial_amount_type' => '',
            'partial_amount' => '',
            'stock_amount' => '60',
            'stock_status' => '1',
            'product_status' => 'published',
            'position_order' => '0',
        ])
        ->assertRedirect();

    $product->refresh();

    // Still ৳10 with no discount — no inflation, no phantom discount.
    expect($product->getRawOriginal('price'))->toBe(1000)
        ->and($product->getRawOriginal('discount_price'))->toBeNull();
});

/**
 * The trap the owner likely hit: a product that already has a discount, then
 * the price is lowered BELOW the old discount. The `discount_price < price`
 * rule rejects the save, so the product silently keeps its old price + discount
 * — looking as if "10 turned into 2000 with a discount I didn't add".
 */
it('rejects lowering price below an existing discount and reports the error', function () {
    $category = Category::factory()->create();

    $product = Product::factory()->create([
        'category_id' => $category->id,
        'title' => 'test chair',
        'sku' => 'ST665956',
        'price' => Money::fromDisplay(2000),
        'discount_price' => Money::fromDisplay(1200),
        'product_status' => 'published',
        'stock_amount' => 60,
    ]);

    $response = $this->actingAs($this->manager)
        ->from("/admin/catalog/products/{$product->id}/edit")
        ->put("/admin/catalog/products/{$product->id}", [
            'category_id' => $category->id,
            'title' => 'test chair',
            'sku' => 'ST665956',
            'price' => '10',
            'discount_price' => '1200', // left over from the old value
            'is_advance_payment' => '1',
            'advance_payment_type' => 'full',
            'stock_amount' => '60',
            'stock_status' => '1',
            'product_status' => 'published',
            'position_order' => '0',
        ]);

    // Validation fails → redirect back with a discount_price error.
    $response->assertRedirect("/admin/catalog/products/{$product->id}/edit");
    $response->assertSessionHasErrors('discount_price');

    // The product is UNCHANGED — this is what looks like "price became 2000 again".
    $product->refresh();
    expect($product->getRawOriginal('price'))->toBe(200000)
        ->and($product->getRawOriginal('discount_price'))->toBe(120000);
});
