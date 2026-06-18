<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class CategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    public function __construct(Category $category)
    {
        parent::__construct($category);
    }

    /** @param array<string,mixed> $attributes */
    public function create(array $attributes): Category
    {
        return Category::query()->create($attributes);
    }

    public function findBySlug(string $slug): ?Category
    {
        return Category::query()->where('slug', $slug)->first();
    }

    public function findActiveBySlug(string $slug): ?Category
    {
        return Category::query()->where('status', true)->where('slug', $slug)->first();
    }

    /** @return Collection<int, Category> */
    public function activeOrdered(): Collection
    {
        return Category::query()
            ->where('status', true)
            ->orderBy('position_order')
            ->orderBy('title')
            ->get();
    }
}
