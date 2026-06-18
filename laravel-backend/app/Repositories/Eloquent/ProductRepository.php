<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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

    /**
     * @param  array<string,mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function adminPaginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters(Product::query()->with('category'), $filters)->paginate($perPage);
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return Collection<int, Product>
     */
    public function allMatching(array $filters): Collection
    {
        return $this->applyFilters(Product::query(), $filters)->get();
    }

    /** @return LengthAwarePaginator<int, Product> */
    public function trashedPaginate(int $perPage = 20): LengthAwarePaginator
    {
        return Product::onlyTrashed()->latest('deleted_at')->paginate($perPage);
    }

    public function findWithTrashed(int $id): ?Product
    {
        return Product::withTrashed()->find($id);
    }

    /**
     * @param  Builder<Product>  $query
     * @param  array<string,mixed>  $filters
     * @return Builder<Product>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (filled($filters['search'] ?? null)) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('product_status', $filters['status']);
        }

        if (filled($filters['category_id'] ?? null)) {
            $query->where('category_id', $filters['category_id']);
        }

        if (array_key_exists('stock_status', $filters) && $filters['stock_status'] !== null) {
            $query->where('stock_status', (bool) $filters['stock_status']);
        }

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (filled($filters['to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $allowedSorts = ['position_order', 'title', 'price', 'created_at', 'stock_amount'];
        $sort = in_array($filters['sort'] ?? null, $allowedSorts, true) ? $filters['sort'] : 'position_order';
        $direction = (($filters['dir'] ?? 'asc') === 'desc') ? 'desc' : 'asc';

        return $query->orderBy($sort, $direction);
    }
}
