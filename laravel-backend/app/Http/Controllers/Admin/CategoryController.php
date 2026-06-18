<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\Catalog\CategoryService;
use Illuminate\Http\JsonResponse;

final class CategoryController
{
    public function __construct(private readonly CategoryService $service) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => CategoryResource::collection(
                Category::query()->orderBy('position_order')->orderBy('title')->get()
            ),
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->service->create($request->validated());

        return response()->json(['data' => new CategoryResource($category)], 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $updated = $this->service->update($category, $request->validated());

        return response()->json(['data' => new CategoryResource($updated)]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->service->delete($category);

        return response()->json(status: 204);
    }
}
