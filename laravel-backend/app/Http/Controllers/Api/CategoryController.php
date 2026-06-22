<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CategoryController
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->categories->activeOrdered());
    }

    public function show(string $slug): JsonResponse
    {
        $category = $this->categories->findActiveBySlug($slug);

        abort_if($category === null, 404);

        $products = $this->products->paginatePublishedForCategory($category->id, 20);

        return response()->json([
            'data' => new CategoryResource($category),
            'products' => ProductResource::collection($products->items()),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }
}
