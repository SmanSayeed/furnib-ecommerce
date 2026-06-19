<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Product;
use Carbon\CarbonInterface;

/**
 * Builds the storefront sitemap entry list. Only published products and active
 * categories are exposed; URLs point at the public storefront base.
 */
final class SitemapBuilder
{
    /**
     * @return list<array{loc: string, lastmod: string|null}>
     */
    public function entries(): array
    {
        $base = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');

        $urls = [['loc' => $base, 'lastmod' => null]];

        Category::query()->active()->get(['slug', 'updated_at'])
            ->each(function (Category $category) use (&$urls, $base): void {
                $urls[] = [
                    'loc' => $base.'/category/'.$category->slug,
                    'lastmod' => $this->date($category->updated_at),
                ];
            });

        Product::query()->published()->get(['slug', 'updated_at'])
            ->each(function (Product $product) use (&$urls, $base): void {
                $urls[] = [
                    'loc' => $base.'/products/'.$product->slug,
                    'lastmod' => $this->date($product->updated_at),
                ];
            });

        return $urls;
    }

    private function date(?CarbonInterface $date): ?string
    {
        return $date?->toAtomString();
    }
}
