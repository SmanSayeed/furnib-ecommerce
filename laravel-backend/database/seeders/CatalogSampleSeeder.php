<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Local-only sample catalog data. Run with:
 *   php artisan db:seed --class=CatalogSampleSeeder
 */
class CatalogSampleSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['title' => 'Chair', 'slug' => 'chair', 'order' => 1],
            ['title' => 'Table', 'slug' => 'table', 'order' => 2],
        ];

        foreach ($definitions as $definition) {
            $category = Category::firstOrCreate(
                ['slug' => $definition['slug']],
                [
                    'title' => $definition['title'],
                    'details' => $definition['title'].' collection for home, office and commercial use.',
                    'status' => true,
                    'position_order' => $definition['order'],
                ],
            );

            if ($category->products()->count() === 0) {
                Product::factory()->count(5)->create([
                    'category_id' => $category->id,
                    'product_status' => 'published',
                ]);
            }
        }
    }
}
