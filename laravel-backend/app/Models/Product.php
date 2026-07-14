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
 * @property bool $shipping_charge_allowed
 * @property bool $multi_qty_shipping_enabled Cheaper delivery for each unit after the first
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
        'stock_amount', 'stock_status', 'shipping_charge_allowed', 'multi_qty_shipping_enabled',
        'meta_title', 'meta_description', 'og_image',
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
            'shipping_charge_allowed' => 'boolean',
            'multi_qty_shipping_enabled' => 'boolean',
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

    /** @return HasMany<ProductShippingCharge, $this> */
    public function shippingCharges(): HasMany
    {
        return $this->hasMany(ProductShippingCharge::class);
    }

    /**
     * The extra delivery cost (paisa) this product adds to ONE ORDER LINE of the
     * given quantity, in the given zone. Excludes the zone's base cost, which is
     * charged once per order.
     *
     *   option on + a rate for the zone → extra + multi × (qty − 1)
     *   otherwise                       → extra × qty
     *
     * One van goes out either way, so the units after the first cost less to carry.
     * At qty = 1 both branches give `extra`, which is why turning the option on can
     * never change a single-item order.
     *
     * Uses the loaded `shippingCharges` relation when available to avoid N+1.
     */
    public function extraMinorFor(int $zoneId, int $qty = 1): int
    {
        // A product marked "no delivery charge" never adds an extra, regardless of
        // any rows left behind in shipping_charges.
        if (! $this->shipping_charge_allowed) {
            return 0;
        }

        $qty = max(1, $qty);
        $charge = $this->shippingCharges->firstWhere('shipping_zone_id', $zoneId);

        if ($charge === null) {
            return 0;
        }

        $extra = $charge->extra_cost->toMinor();
        $multi = $charge->multi_extra_cost;

        // NULL means "not configured" and falls back to per-unit. Zero, by
        // contrast, is a deliberate value — the later units then ship free.
        if (! $this->multi_qty_shipping_enabled || ! $multi instanceof Money) {
            return $extra * $qty;
        }

        return $extra + $multi->toMinor() * ($qty - 1);
    }

    /**
     * The discount, but only when it is a REAL one: non-null and strictly below
     * the regular price. A stored discount that is >= price (a legacy row, or one
     * written before Catalog\UpdateProductRequest gained its `lt:price` rule) is
     * ignored — the effective price can only ever go DOWN, never up.
     *
     * Zero is a legitimate discount: the admin form validates discount_price as
     * min:0 | lt:price, so a deliberately free product is a valid input.
     */
    public function effectiveDiscount(): ?Money
    {
        $discount = $this->discount_price;

        if (! $discount instanceof Money) {
            return null;
        }

        return $discount->toMinor() < $this->price->toMinor() ? $discount : null;
    }

    /**
     * What this product ACTUALLY costs. The single source of truth for pricing —
     * used by order placement, the public API, the product feed and the marketing
     * events, so no surface can ever advertise a price the server won't charge.
     */
    public function effectivePrice(): Money
    {
        return $this->effectiveDiscount() ?? $this->price;
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
