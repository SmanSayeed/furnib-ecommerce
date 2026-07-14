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
 * Optional extra delivery cost for a product within a specific shipping zone, on
 * top of the zone's base cost.
 *
 * `extra_cost` is charged per unit — unless the product has the multi-quantity
 * option on and this row carries a `multi_extra_cost`, in which case the first
 * unit pays `extra_cost` and every further unit pays `multi_extra_cost`.
 *
 * @property int $id
 * @property int $product_id
 * @property int $shipping_zone_id
 * @property Money $extra_cost Charge for the first unit (or every unit, when the option is off)
 * @property Money|null $multi_extra_cost Charge for each unit AFTER the first. NULL = not configured.
 */
class ProductShippingCharge extends Model
{
    /** @use HasFactory<ProductShippingChargeFactory> */
    use HasFactory;

    protected $fillable = ['product_id', 'shipping_zone_id', 'extra_cost', 'multi_extra_cost'];

    protected function casts(): array
    {
        return [
            'extra_cost' => MoneyCast::class,
            'multi_extra_cost' => MoneyCast::class,
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
