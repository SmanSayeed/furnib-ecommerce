<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface extends RepositoryInterface
{
    /** @param array<string,mixed> $attributes */
    public function create(array $attributes): Product;

    public function findPublishedBySlug(string $slug): ?Product;

    /** @return LengthAwarePaginator<int, Product> */
    public function paginatePublishedForCategory(int $categoryId, int $perPage = 12): LengthAwarePaginator;
}
