<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Repositories\Contracts\ProductRepositoryInterface;
use League\Csv\Writer;

final class ExportProductsCsv
{
    public function __construct(private readonly ProductRepositoryInterface $products) {}

    /**
     * @param  array<string,mixed>  $filters
     */
    public function handle(array $filters): string
    {
        $writer = Writer::createFromString();
        $writer->insertOne([
            'id', 'title', 'sku', 'slug', 'category',
            'price', 'discount_price', 'status', 'stock_amount', 'stock_status',
        ]);

        foreach ($this->products->allMatching($filters) as $product) {
            $writer->insertOne([
                $product->id,
                $product->title,
                $product->sku,
                $product->slug,
                $product->category?->title,
                $product->price->toDisplay(),
                $product->discount_price?->toDisplay(),
                $product->product_status,
                $product->stock_amount,
                $product->stock_status ? '1' : '0',
            ]);
        }

        return $writer->toString();
    }
}
