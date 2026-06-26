<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Product;
use App\Support\Lists\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductRepositoryInterface extends RepositoryInterface
{
    /** @param array<string,mixed> $attributes */
    public function create(array $attributes): Product;

    public function findPublishedBySlug(string $slug): ?Product;

    /** @return LengthAwarePaginator<int, Product> */
    public function paginatePublishedForCategory(int $categoryId, int $perPage = 12): LengthAwarePaginator;

    /**
     * @param  array<string,mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function adminPaginate(array $filters, int $perPage = 20): LengthAwarePaginator;

    /** @return LengthAwarePaginator<int, Product> */
    public function adminList(ListQuery $query): LengthAwarePaginator;

    /**
     * @param  array<string,mixed>  $filters
     * @return Collection<int, Product>
     */
    public function allMatching(array $filters): Collection;

    /** @return LengthAwarePaginator<int, Product> */
    public function trashedPaginate(int $perPage = 20): LengthAwarePaginator;

    public function findWithTrashed(int $id): ?Product;
}
