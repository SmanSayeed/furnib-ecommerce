<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

interface CategoryRepositoryInterface extends RepositoryInterface
{
    /** @param array<string,mixed> $attributes */
    public function create(array $attributes): Category;

    public function findBySlug(string $slug): ?Category;

    public function findActiveBySlug(string $slug): ?Category;

    /** @return Collection<int, Category> */
    public function activeOrdered(): Collection;
}
