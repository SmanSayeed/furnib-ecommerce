<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        /** @var array<int,string> $words */
        $words = fake()->unique()->words(3);
        $title = ucwords(implode(' ', $words));

        return [
            'category_id' => Category::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'sku' => 'FNB-'.fake()->unique()->numerify('######'),
            'details' => fake()->paragraph(),
            // display amount; the Money cast stores it as minor units
            'price' => fake()->randomFloat(2, 500, 50000),
            'main_image' => 'products/sample.webp',
            'is_featured' => false,
            'is_new' => false,
            'position_order' => fake()->numberBetween(0, 50),
            'product_status' => 'published',
            'stock_amount' => fake()->numberBetween(1, 100),
            'stock_status' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['product_status' => 'draft']);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (): array => ['stock_status' => false]);
    }
}
