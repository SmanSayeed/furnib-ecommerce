<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Repositories\Contracts\ProductRepositoryInterface;
use DomainException;
use Illuminate\Support\Str;

final class ProductService
{
    private const MAX_GALLERY_IMAGES = 6;

    public function __construct(private readonly ProductRepositoryInterface $products) {}

    /**
     * @param  array<string,mixed>  $data
     */
    public function create(array $data): Product
    {
        if (blank($data['slug'] ?? null)) {
            $data['slug'] = $this->uniqueSlug((string) $data['title']);
        }

        if (blank($data['sku'] ?? null)) {
            $data['sku'] = $this->generateSku();
        }

        return $this->products->create($data);
    }

    /**
     * Add a gallery image, enforcing the six-image maximum.
     *
     * @param  array<string,mixed>  $data
     */
    public function addImage(Product $product, array $data): ProductImage
    {
        if ($product->images()->count() >= self::MAX_GALLERY_IMAGES) {
            throw new DomainException('A product may have at most '.self::MAX_GALLERY_IMAGES.' gallery images.');
        }

        return $product->images()->create($data);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base);
        $candidate = $slug;
        $suffix = 1;

        while (Product::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.(++$suffix);
        }

        return $candidate;
    }

    private function generateSku(): string
    {
        do {
            $sku = 'FNB-'.mb_strtoupper(Str::random(8));
        } while (Product::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }
}
