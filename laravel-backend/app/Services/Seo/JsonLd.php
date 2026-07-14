<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Product;

/**
 * Builds Schema.org JSON-LD structures for SEO rich results. Pure data — the
 * storefront embeds the returned arrays as <script type="application/ld+json">.
 */
final class JsonLd
{
    /**
     * @return array<string, mixed>
     */
    public function product(Product $product, ?string $url = null, ?string $image = null): array
    {
        $price = $product->effectivePrice();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->title,
            'sku' => $product->sku,
            'description' => $product->meta_description ?: strip_tags((string) $product->details),
            'image' => $image,
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => 'BDT',
                'price' => number_format($price->toDisplay(), 2, '.', ''),
                'availability' => $product->isInStock()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => $url,
            ],
        ];
    }

    /**
     * @param  list<array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public function breadcrumb(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                static fn (array $item, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $item['name'],
                    'item' => $item['url'],
                ],
                $items,
                array_keys($items),
            ),
        ];
    }
}
