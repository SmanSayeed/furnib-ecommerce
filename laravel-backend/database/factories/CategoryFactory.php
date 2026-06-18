<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        /** @var array<int,string> $words */
        $words = fake()->unique()->words(2);
        $title = ucwords(implode(' ', $words));

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'details' => fake()->paragraph(),
            'status' => true,
            'position_order' => fake()->numberBetween(0, 50),
            'meta_title' => $title,
            'meta_description' => fake()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => false]);
    }
}
