<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Models\Product;
use App\Storage\Contracts\StorageRepository;
use RuntimeException;

/**
 * Builds the Meta/Google product feed. Only published products are exposed;
 * availability reflects the real stock logic.
 */
final class ProductFeed
{
    public const HEADERS = [
        'id', 'title', 'description', 'availability', 'condition',
        'price', 'sale_price', 'link', 'image_link', 'additional_image_link', 'brand',
    ];

    public function __construct(private readonly StorageRepository $storage) {}

    /**
     * @return array<int, array<string, string>>
     */
    public function rows(): array
    {
        $base = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
        $brand = (string) config('app.name');

        return Product::query()->published()->with('images')->get()
            ->map(fn (Product $p): array => [
                // content_ids in the Pixel/CAPI MUST equal this id — both use the SKU.
                'id' => $p->sku !== '' ? $p->sku : (string) $p->id,
                'title' => $p->title,
                'description' => $p->meta_description ?: strip_tags((string) $p->details),
                'availability' => $p->isInStock() ? 'in stock' : 'out of stock',
                'condition' => 'new',
                // price is always the regular price; sale_price carries the discount.
                'price' => number_format($p->price->toDisplay(), 2, '.', '').' BDT',
                'sale_price' => $p->discount_price !== null
                    ? number_format($p->discount_price->toDisplay(), 2, '.', '').' BDT'
                    : '',
                // Real storefront landing page (must not 404 — catalog ads link here).
                'link' => $base.'/product/'.$p->slug,
                'image_link' => $this->imageUrl($p->main_image),
                'additional_image_link' => $this->additionalImages($p),
                'brand' => $brand,
            ])
            ->values()
            ->all();
    }

    /**
     * Up to 10 extra gallery images, comma-separated, as Meta/Google expect.
     */
    private function additionalImages(Product $product): string
    {
        return $product->images
            ->take(10)
            ->map(fn ($image): string => $this->imageUrl($image->path))
            ->filter(static fn (string $url): bool => $url !== '')
            ->implode(',');
    }

    public function csv(): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open a temporary stream for the feed.');
        }

        fputcsv($handle, self::HEADERS);

        foreach ($this->rows() as $row) {
            fputcsv($handle, array_values($row));
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function imageUrl(?string $path): string
    {
        if (! is_string($path) || $path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->storage->url($path);
    }
}
