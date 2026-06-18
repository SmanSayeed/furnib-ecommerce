<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'path' => 'products/gallery/'.fake()->unique()->numerify('####').'.webp',
            'alt_text' => fake()->sentence(3),
            'position' => fake()->numberBetween(0, 5),
        ];
    }
}
