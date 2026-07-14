<?php

declare(strict_types=1);

use App\Models\Product;
use Tests\TestCase;

// Eloquent needs a booted app for its casts; no DB is touched (nothing is saved).
uses(TestCase::class);

/**
 * The effective-price rule is the single source of truth for "what does this
 * product actually cost". A discount counts only when it is strictly BELOW the
 * regular price — so a bad row can never RAISE the price.
 *
 * No DB: the Money cast runs on an unsaved model.
 */
function productPriced(int|float $price, int|float|null $discount = null): Product
{
    return new Product([
        'price' => $price,
        'discount_price' => $discount,
    ]);
}

it('uses the regular price when no discount is set', function () {
    $product = productPriced(1000);

    expect($product->effectiveDiscount())->toBeNull()
        ->and($product->effectivePrice()->toMinor())->toBe(100000);
});

it('uses the discount when it is below the regular price', function () {
    $product = productPriced(1000, 800);

    expect($product->effectiveDiscount()?->toMinor())->toBe(80000)
        ->and($product->effectivePrice()->toMinor())->toBe(80000);
});

it('treats a zero discount as a deliberately free product', function () {
    // The admin form validates discount_price as min:0 | lt:price, so 0 is a
    // legal, intentional input (giveaway / bundled item). Guarding it away would
    // silently charge full price for something the owner made free.
    $product = productPriced(1000, 0);

    expect($product->effectiveDiscount()?->toMinor())->toBe(0)
        ->and($product->effectivePrice()->toMinor())->toBe(0);
});

it('ignores a discount equal to the price', function () {
    $product = productPriced(1000, 1000);

    expect($product->effectiveDiscount())->toBeNull()
        ->and($product->effectivePrice()->toMinor())->toBe(100000);
});

it('never raises the price when a legacy discount is above it', function () {
    // Catalog\UpdateProductRequest was missing `lt:price`, so such rows can exist
    // in production. They must be ignored, not honoured.
    $product = productPriced(1000, 1200);

    expect($product->effectiveDiscount())->toBeNull()
        ->and($product->effectivePrice()->toMinor())->toBe(100000);
});

it('handles a free product with no discount', function () {
    $product = productPriced(0);

    expect($product->effectiveDiscount())->toBeNull()
        ->and($product->effectivePrice()->toMinor())->toBe(0);
});
