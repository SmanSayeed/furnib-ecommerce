<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $product)
    {
        parent::__construct($product);
    }

    /** @param array<string,mixed> $attributes */
    public function create(array $attributes): Product
    {
        return Product::query()->create($attributes);
    }

    public function findPublishedBySlug(string $slug): ?Product
    {
        return Product::query()
            ->where('product_status', 'published')
            ->where('slug', $slug)
            ->with('images')
            ->first();
    }

    /** @return LengthAwarePaginator<int, Product> */
    public function paginatePublishedForCategory(int $categoryId, int $perPage = 12): LengthAwarePaginator
    {
        return Product::query()
            ->where('product_status', 'published')
            ->where('category_id', $categoryId)
            ->orderBy('position_order')
            ->orderBy('title')
            ->with('images')
            ->paginate($perPage);
    }
}
