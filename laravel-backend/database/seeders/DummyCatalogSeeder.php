<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Settings\SettingsService;
use App\Storage\Contracts\StorageRepository;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds real demo catalog data from the dummy-products image folders.
 *
 * Folder layout (repo root):
 *   dummy-products/<category>/<product>/<image>.avif (2..6 images per product)
 *
 * Images are copied into the active storage driver (server disk / R2) so the
 * storefront renders them through the normal media URL pipeline. Idempotent:
 * re-running will not duplicate categories, products or images.
 *
 * Run with PHP 8.3:
 *   php artisan db:seed --class=DummyCatalogSeeder
 */
class DummyCatalogSeeder extends Seeder
{
    /** Max images per product (catalog rule: 1 main + up to 6 total). */
    private const MAX_IMAGES = 6;

    /** Indicative price ranges (display BDT) per category slug. */
    private const PRICE_RANGES = [
        'table' => [7500, 26000],
        'chair' => [3200, 13500],
        'decor-item' => [750, 4800],
        'default' => [1500, 15000],
    ];

    public function run(): void
    {
        $root = dirname(base_path()).DIRECTORY_SEPARATOR.'dummy-products';

        if (! is_dir($root)) {
            $this->command->warn("dummy-products folder not found at: {$root}");

            return;
        }

        $storage = app(StorageRepository::class);
        $position = 1;

        foreach ($this->subDirectories($root) as $categoryPath) {
            // `banners/` holds home-page banner art, not a product category.
            if (strtolower(basename($categoryPath)) === 'banners') {
                continue;
            }

            $categorySlug = Str::slug(basename($categoryPath));
            $category = Category::firstOrCreate(
                ['slug' => $categorySlug],
                [
                    'title' => $this->humanize(basename($categoryPath)),
                    'details' => $this->humanize(basename($categoryPath))
                        .' collection — curated for home, office and commercial spaces.',
                    'status' => true,
                    'position_order' => $position++,
                ],
            );

            $featuredPicked = false;

            foreach ($this->subDirectories($categoryPath) as $productPath) {
                $this->seedProduct($category, $productPath, $storage, $featuredPicked);
            }

            $this->backfillCategoryImage($category);
        }

        $this->seedBanners($storage);

        $this->command->info('Dummy catalog seeded from AVIF images.');
    }

    /**
     * Seed the two home-page banners from dummy-products/banners (idempotent).
     * The SSLCommerz payment logo is excluded (it is a static footer asset).
     */
    private function seedBanners(StorageRepository $storage): void
    {
        $dir = dirname(base_path()).DIRECTORY_SEPARATOR.'dummy-products'.DIRECTORY_SEPARATOR.'banners';
        if (! is_dir($dir)) {
            return;
        }

        $files = glob($dir.DIRECTORY_SEPARATOR.'*.{avif,webp,png,jpg,jpeg}', GLOB_BRACE) ?: [];
        $files = array_values(array_filter(
            $files,
            fn (string $f): bool => ! str_contains(strtolower(basename($f)), 'sslcommerz'),
        ));
        sort($files);

        $settings = app(SettingsService::class);

        foreach ([1, 2] as $i) {
            $file = $files[$i - 1] ?? null;
            $key = 'banner_'.$i;

            if ($file === null || filled($settings->get('branding', $key))) {
                continue;
            }

            $contents = file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            $path = $storage->put('branding/'.basename($file), $contents);
            $settings->set('branding', $key, $path);
            $this->command->info("  • banner {$i} -> {$path}");
        }
    }

    private function seedProduct(
        Category $category,
        string $productPath,
        StorageRepository $storage,
        bool &$featuredPicked,
    ): void {
        $folder = basename($productPath);
        $slug = Str::slug($folder);
        $title = $this->productTitle($folder);

        $images = $this->imageFiles($productPath);
        if ($images === []) {
            return;
        }

        $existing = Product::withTrashed()->where('slug', $slug)->first();
        if ($existing && $existing->images()->exists()) {
            // Already fully seeded — leave it untouched.
            return;
        }

        [$min, $max] = self::PRICE_RANGES[$category->slug] ?? self::PRICE_RANGES['default'];
        $price = (float) random_int($min, $max);
        $hasDiscount = random_int(0, 2) === 0; // ~1 in 3
        $discount = $hasDiscount ? round($price * (random_int(80, 92) / 100), 2) : null;

        $isFeatured = ! $featuredPicked;
        $featuredPicked = $featuredPicked || $isFeatured;

        $product = $existing ?? new Product;
        $product->fill([
            'category_id' => $category->id,
            'title' => $title,
            'slug' => $slug,
            'sku' => 'FNB-'.strtoupper(substr(md5($slug), 0, 6)),
            'details' => $title.' — premium build quality, comfortable and durable. '
                .'Ready stock with fast delivery across Bangladesh.',
            'price' => Money::fromDisplay($price),
            'discount_price' => $discount !== null ? Money::fromDisplay($discount) : null,
            'is_featured' => $isFeatured,
            'is_new' => random_int(0, 1) === 1,
            'product_status' => 'published',
            'stock_amount' => random_int(5, 80),
            'stock_status' => true,
        ]);
        $product->save();

        $images = array_slice($images, 0, self::MAX_IMAGES);
        $storedPaths = [];

        foreach ($images as $i => $file) {
            $contents = file_get_contents($file);
            if ($contents === false) {
                continue;
            }
            $stored = $storage->put(
                'products/'.$slug.'/'.basename($file),
                $contents,
            );
            $storedPaths[] = $stored;

            if ($i === 0) {
                $product->main_image = $stored;
                $product->save();
            }

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $stored,
                'alt_text' => $title,
                'position' => $i + 1,
            ]);
        }

        $this->command->info("  • {$category->slug}/{$slug} (".count($storedPaths).' images)');
    }

    /**
     * Use the first product's main image as the category header/thumbnail
     * when none has been set yet.
     */
    private function backfillCategoryImage(Category $category): void
    {
        if (filled($category->header_image) && filled($category->thumbnail_image)) {
            return;
        }

        $img = $category->products()
            ->whereNotNull('main_image')
            ->orderBy('position_order')
            ->orderBy('id')
            ->value('main_image');

        if (! is_string($img) || $img === '') {
            return;
        }

        $category->header_image = $category->header_image ?: $img;
        $category->thumbnail_image = $category->thumbnail_image ?: $img;
        $category->save();
    }

    /**
     * Sorted list of immediate sub-directories.
     *
     * @return list<string>
     */
    private function subDirectories(string $path): array
    {
        $dirs = glob($path.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [];
        sort($dirs);

        return $dirs;
    }

    /**
     * Sorted image files inside a product folder.
     *
     * @return list<string>
     */
    private function imageFiles(string $path): array
    {
        $files = glob($path.DIRECTORY_SEPARATOR.'*.{avif,webp,png,jpg,jpeg}', GLOB_BRACE) ?: [];
        sort($files);

        return $files;
    }

    private function humanize(string $slug): string
    {
        return Str::title(str_replace('-', ' ', $slug));
    }

    /** Humanize a product folder, stripping a trailing model code (e.g. -ch01eo). */
    private function productTitle(string $folder): string
    {
        $cleaned = preg_replace('/-[a-z]{2}\d{2}[a-z]{0,3}$/i', '', $folder) ?? $folder;

        return $this->humanize($cleaned !== '' ? $cleaned : $folder);
    }
}
