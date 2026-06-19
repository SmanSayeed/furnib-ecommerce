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
    public const HEADERS = ['id', 'title', 'description', 'availability', 'condition', 'price', 'link', 'image_link', 'brand'];

    public function __construct(private readonly StorageRepository $storage) {}

    /**
     * @return array<int, array<string, string>>
     */
    public function rows(): array
    {
        $base = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
        $brand = (string) config('app.name');

        return Product::query()->published()->get()
            ->map(fn (Product $p): array => [
                'id' => $p->sku !== '' ? $p->sku : (string) $p->id,
                'title' => $p->title,
                'description' => $p->meta_description ?: strip_tags((string) $p->details),
                'availability' => $p->isInStock() ? 'in stock' : 'out of stock',
                'condition' => 'new',
                'price' => number_format(($p->discount_price ?? $p->price)->toDisplay(), 2, '.', '').' BDT',
                'link' => $base.'/products/'.$p->slug,
                'image_link' => $this->imageUrl($p->main_image),
                'brand' => $brand,
            ])
            ->values()
            ->all();
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
