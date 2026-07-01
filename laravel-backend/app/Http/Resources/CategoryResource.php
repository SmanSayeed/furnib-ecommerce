<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesMediaUrls;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
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
            'details' => $this->details,
            'header_image' => $this->mediaUrl($this->header_image),
            'header_mobile_url' => $this->mediaUrl($this->header_image_mobile),
            'thumbnail_image' => $this->mediaUrl($this->thumbnail_image),
            'position_order' => $this->position_order,
            'seo' => [
                'meta_title' => $this->meta_title ?: $this->title,
                'meta_description' => $this->meta_description,
                'og_image' => $this->mediaUrl($this->og_image ?: $this->thumbnail_image),
            ],
        ];
    }
}
