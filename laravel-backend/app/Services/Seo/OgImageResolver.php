<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Product;
use App\Services\Settings\SettingsService;

/**
 * Resolves the Open Graph / social thumbnail for each surface using a fallback
 * chain. Returns the stored path/URL (or null); the caller turns it into a
 * public URL. Home → branding banner; product/category → their own image.
 */
final class OgImageResolver
{
    public function __construct(private readonly SettingsService $settings) {}

    public function forProduct(Product $product): ?string
    {
        return $product->og_image
            ?: $product->social_thumbnail_image
            ?: $product->main_image
            ?: $this->home();
    }

    public function forCategory(Category $category): ?string
    {
        return $category->og_image
            ?: $category->header_image
            ?: $category->thumbnail_image
            ?: $this->home();
    }

    public function home(): ?string
    {
        $branding = $this->settings->toArray('branding');

        return $branding['banner_1'] ?? $branding['logo_light'] ?? null;
    }
}
