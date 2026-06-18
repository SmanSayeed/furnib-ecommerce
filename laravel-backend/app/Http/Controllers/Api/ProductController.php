<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\ProductResource;
use App\Repositories\Contracts\ProductRepositoryInterface;

final class ProductController
{
    public function __construct(private readonly ProductRepositoryInterface $products) {}

    public function show(string $slug): ProductResource
    {
        $product = $this->products->findPublishedBySlug($slug);

        abort_if($product === null, 404);

        return new ProductResource($product);
    }
}
