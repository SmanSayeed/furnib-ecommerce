<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Models\Product;
use App\Models\ShippingZone;
use Illuminate\Support\Collection;

/**
 * The one place that knows what delivery costs.
 *
 *   shipping = (any chargeable line ? zone.cost : 0)
 *            + Σ over chargeable lines [ product.extraPerUnitMinorFor(zone) × qty ]
 *
 * The zone base is charged ONCE per order (not per product), and only when at
 * least one line is chargeable — an all-free-shipping cart ships free. A product
 * flagged `shipping_charge_allowed = false` contributes nothing: not its per-unit
 * extra, and not a share of the base.
 *
 * Lifted verbatim out of PlaceOrder so that order placement, an admin zone change,
 * and (next) the admin order-create page cannot drift apart. All amounts are
 * integer minor units (paisa).
 */
final class ShippingCalculator
{
    /**
     * @param  Collection<int, array{product: Product, qty: int}>  $lines
     */
    public function minorFor(Collection $lines, ?ShippingZone $zone): int
    {
        if ($zone === null) {
            return 0;
        }

        $minor = 0;
        $anyChargeable = false;

        foreach ($lines as $line) {
            $product = $line['product'];

            if (! $product->shipping_charge_allowed) {
                continue;
            }

            $anyChargeable = true;
            $minor += $product->extraPerUnitMinorFor($zone->id) * $line['qty'];
        }

        return $anyChargeable ? $minor + $zone->cost->toMinor() : $minor;
    }
}
