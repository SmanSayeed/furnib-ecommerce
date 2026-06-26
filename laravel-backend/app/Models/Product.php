<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Concerns\AppliesListFilters;
use App\Concerns\Auditable;
use App\Support\Money;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $category_id
 * @property string $title
 * @property string $slug
 * @property string $sku
 * @property Money $price
 * @property Money|null $discount_price
 * @property string|null $main_image
 * @property string|null $social_thumbnail_image
 * @property bool $stock_status
 * @property int $stock_amount
 * @property string $product_status
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use AppliesListFilters, Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'title', 'slug', 'sku', 'details', 'product_video',
        'main_image', 'social_thumbnail_image', 'price', 'discount_price',
        'is_advance_payment', 'advance_payment_type', 'partial_amount_type', 'partial_amount',
        'is_featured', 'is_new', 'position_order', 'product_status',
        'stock_amount', 'stock_status', 'meta_title', 'meta_description', 'og_image',
    ];

    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class,
            'discount_price' => MoneyCast::class,
            'is_advance_payment' => 'boolean',
            'is_featured' => 'boolean',
            'is_new' => 'boolean',
            'stock_status' => 'boolean',
            'stock_amount' => 'integer',
            'position_order' => 'integer',
            'partial_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /** @param Builder<Product> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('product_status', 'published');
    }

    public function isInStock(): bool
    {
        return $this->stock_status && $this->stock_amount > 0;
    }

    public function resolvedSocialThumbnail(): ?string
    {
        return $this->social_thumbnail_image ?: $this->main_image;
    }
}
