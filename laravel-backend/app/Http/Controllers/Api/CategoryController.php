<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\CategoryResource;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CategoryController
{
    public function __construct(private readonly CategoryRepositoryInterface $categories) {}

    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->categories->activeOrdered());
    }

    public function show(string $slug): CategoryResource
    {
        $category = $this->categories->findActiveBySlug($slug);

        abort_if($category === null, 404);

        return new CategoryResource($category);
    }
}
