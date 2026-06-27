<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\ProductShippingChargeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Optional extra delivery cost for a product within a specific shipping zone,
 * charged per unit on top of the zone's base cost.
 *
 * @property int $id
 * @property int $product_id
 * @property int $shipping_zone_id
 * @property Money $extra_cost
 */
class ProductShippingCharge extends Model
{
    /** @use HasFactory<ProductShippingChargeFactory> */
    use HasFactory;

    protected $fillable = ['product_id', 'shipping_zone_id', 'extra_cost'];

    protected function casts(): array
    {
        return [
            'extra_cost' => MoneyCast::class,
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ShippingZone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
