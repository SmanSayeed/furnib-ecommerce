<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Catalog\ExportProductsCsv;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\Catalog\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProductController
{
    /** @var array<int,string> */
    private const FILTER_KEYS = ['search', 'status', 'category_id', 'stock_status', 'from', 'to', 'sort', 'dir'];

    public function __construct(
        private readonly ProductService $service,
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->products->adminPaginate($request->only(self::FILTER_KEYS));

        return ProductResource::collection($paginator)->response();
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->service->create($request->validated());

        return response()->json(['data' => new ProductResource($product->load('images'))], 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return response()->json(['data' => new ProductResource($product->refresh()->load('images'))]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(status: 204);
    }

    public function trashed(): JsonResponse
    {
        return response()->json($this->products->trashedPaginate());
    }

    public function restore(int $id): JsonResponse
    {
        $product = $this->products->findWithTrashed($id);
        abort_if($product === null, 404);

        $product->restore();

        return response()->json(['data' => new ProductResource($product)]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $product = $this->products->findWithTrashed($id);
        abort_if($product === null, 404);

        $product->forceDelete();

        return response()->json(status: 204);
    }

    public function export(Request $request, ExportProductsCsv $export): Response
    {
        $csv = $export->handle($request->only(self::FILTER_KEYS));

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="products.csv"',
        ]);
    }
}
