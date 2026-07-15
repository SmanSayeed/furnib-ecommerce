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
        // Extended Meta fields: the category breadcrumb powers feed filtering,
        // item_group_id groups variants (we have none → equals id), and
        // quantity_to_sell_on_facebook mirrors real stock.
        'product_type', 'item_group_id', 'quantity_to_sell_on_facebook',
    ];

    public function __construct(private readonly StorageRepository $storage) {}

    /**
     * @param  array<int, int>|null  $categoryIds  restrict to these categories (admin export); null = all
     * @return array<int, array<string, string>>
     */
    public function rows(?array $categoryIds = null): array
    {
        $base = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
        $brand = (string) config('app.name');

        return Product::query()->published()
            ->when($categoryIds !== null && $categoryIds !== [], fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->with(['images', 'category'])
            ->get()
            ->map(function (Product $p) use ($base, $brand): array {
                $id = $p->sku !== '' ? $p->sku : (string) $p->id;

                return [
                    // content_ids in the Pixel/CAPI MUST equal this id — both use the SKU.
                    'id' => $id,
                    'title' => $p->title,
                    'description' => $p->meta_description ?: strip_tags((string) $p->details),
                    // Meta consolidated availability to just these two values.
                    'availability' => $p->isInStock() ? 'in stock' : 'out of stock',
                    'condition' => 'new',
                    // price is always the regular price; sale_price carries the discount.
                    // Meta rejects a sale_price that is not strictly below price, so
                    // only an EFFECTIVE discount is emitted.
                    'price' => number_format($p->price->toDisplay(), 2, '.', '').' BDT',
                    'sale_price' => $p->effectiveDiscount() !== null
                        ? number_format($p->effectiveDiscount()->toDisplay(), 2, '.', '').' BDT'
                        : '',
                    // Real storefront landing page (must not 404 — catalog ads link here).
                    'link' => $base.'/product/'.$p->slug,
                    'image_link' => $this->imageUrl($p->main_image),
                    'additional_image_link' => $this->additionalImages($p),
                    'brand' => $brand,
                    'product_type' => (string) $p->category->title,
                    'item_group_id' => $id,
                    'quantity_to_sell_on_facebook' => (string) ($p->isInStock() ? max(0, (int) $p->stock_amount) : 0),
                ];
            })
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

    /**
     * @param  array<int, int>|null  $categoryIds  restrict to these categories (admin export); null = all
     */
    public function csv(?array $categoryIds = null): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open a temporary stream for the feed.');
        }

        fputcsv($handle, self::HEADERS);

        foreach ($this->rows($categoryIds) as $row) {
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
