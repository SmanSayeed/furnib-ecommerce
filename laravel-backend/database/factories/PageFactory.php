<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'body_html' => '<p>'.fake()->paragraph().'</p>',
            'is_published' => true,
            'position' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
