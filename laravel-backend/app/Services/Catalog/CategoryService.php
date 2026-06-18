<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Support\Str;

final class CategoryService
{
    public function __construct(private readonly CategoryRepositoryInterface $categories) {}

    /**
     * @param  array<string,mixed>  $data
     */
    public function create(array $data): Category
    {
        if (blank($data['slug'] ?? null)) {
            $data['slug'] = $this->uniqueSlug((string) $data['title']);
        }

        return $this->categories->create($data);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function update(Category $category, array $data): Category
    {
        $this->categories->update($category, $data);

        return $category->refresh();
    }

    public function delete(Category $category): bool
    {
        return $this->categories->delete($category);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base);
        $candidate = $slug;
        $suffix = 1;

        while (Category::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.(++$suffix);
        }

        return $candidate;
    }
}
