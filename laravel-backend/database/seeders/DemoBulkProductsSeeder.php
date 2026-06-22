<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;

/**
 * DEMO-ONLY: pads every category up to TARGET published products so the
 * storefront infinite-scroll (20 per page) can be exercised end-to-end.
 *
 * It clones the image *paths* of the real seeded products — it does NOT upload
 * anything new (the stored R2/disk keys are simply re-referenced). Idempotent:
 * re-running tops each category up to TARGET and never creates past it.
 *
 *   php artisan db:seed --class=DemoBulkProductsSeeder
 */
class DemoBulkProductsSeeder extends Seeder
{
    private const TARGET = 100;

    public function run(): void
    {
        foreach (Category::all() as $category) {
            $sources = Product::with('images')
                ->where('category_id', $category->id)
                ->whereNotNull('main_image')
                ->where('slug', 'not like', '%-demo-%')
                ->get()
                ->filter(fn (Product $p): bool => $p->images->isNotEmpty())
                ->values();

            if ($sources->isEmpty()) {
                $this->command->warn("  • {$category->slug}: no source product with images — skipped");

                continue;
            }

            $current = Product::where('category_id', $category->id)->count();
            if ($current >= self::TARGET) {
                $this->command->info("  • {$category->slug}: already {$current} (≥ ".self::TARGET.')');

                continue;
            }

            for ($n = $current + 1; $n <= self::TARGET; $n++) {
                $source = $sources[$n % $sources->count()];
                $slug = $category->slug.'-demo-'.$n;

                if (Product::withTrashed()->where('slug', $slug)->exists()) {
                    continue;
                }

                $product = new Product;
                $product->fill([
                    'category_id' => $category->id,
                    'title' => $source->title.' #'.$n,
                    'slug' => $slug,
                    'sku' => 'FNB-'.strtoupper(substr(md5($slug), 0, 6)),
                    'details' => $source->details,
                    'price' => $source->price,
                    'discount_price' => $source->discount_price,
                    'is_featured' => false,
                    'is_new' => false,
                    'product_status' => 'published',
                    'stock_amount' => random_int(5, 80),
                    'stock_status' => true,
                    'main_image' => $source->main_image,
                ]);
                $product->position_order = $n;
                $product->save();

                foreach ($source->images as $img) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'path' => $img->path,
                        'alt_text' => $img->alt_text,
                        'position' => $img->position,
                    ]);
                }
            }

            $this->command->info("  • {$category->slug}: padded to ".self::TARGET);
        }

        $this->command->info('Demo bulk products seeded (image paths reused, no uploads).');
    }
}
