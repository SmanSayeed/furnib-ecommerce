<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\ProductResource;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProductController
{
    public function __construct(private readonly ProductRepositoryInterface $products) {}

    public function show(string $slug): ProductResource
    {
        $product = $this->products->findPublishedBySlug($slug);

        abort_if($product === null, 404);

        return new ProductResource($product);
    }

    /**
     * Storefront header typeahead. Returns a small, capped set of published
     * products matching the query. A blank/too-short query returns nothing so we
     * never scan the whole catalog for one keystroke.
     */
    public function search(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $term = trim((string) ($validated['q'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 8);

        if (mb_strlen($term) < 2) {
            return ProductResource::collection([]);
        }

        return ProductResource::collection($this->products->searchPublished($term, $limit));
    }
}
