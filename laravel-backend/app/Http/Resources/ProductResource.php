<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesMediaUrls;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    use ResolvesMediaUrls;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'details' => $this->details,
            'video' => $this->product_video,
            'main_image' => $this->mediaUrl($this->main_image),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'path' => $this->mediaUrl($image->path),
                'alt' => $image->alt_text,
                'position' => $image->position,
            ])->values()->all()),
            'price' => $this->money($this->price),
            'discount_price' => $this->discount_price instanceof Money ? $this->money($this->discount_price) : null,
            'in_stock' => $this->isInStock(),
            'is_featured' => $this->is_featured,
            'is_new' => $this->is_new,
            'social_thumbnail' => $this->mediaUrl($this->resolvedSocialThumbnail()),
            'seo' => [
                'meta_title' => $this->meta_title ?: $this->title,
                'meta_description' => $this->meta_description,
                'og_image' => $this->mediaUrl($this->og_image ?: $this->resolvedSocialThumbnail()),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function money(Money $money): array
    {
        return [
            'minor' => $money->toMinor(),
            'display' => $money->toDisplay(),
            'formatted' => $money->format(),
        ];
    }
}
