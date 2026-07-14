<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Support\Money;
use Illuminate\Http\JsonResponse;

class ProductShippingZoneController extends Controller
{
    public function __construct(private readonly ProductRepositoryInterface $products) {}

    /**
     * Active shipping zones for a product, each with three figures the storefront
     * needs to price ANY quantity without a round-trip on every click of the
     * stepper:
     *
     *   base                  the zone's base cost (charged once)
     *   extra_per_unit        what the FIRST unit adds
     *   multi_extra_per_unit  what EACH FURTHER unit adds
     *
     * The client then needs one formula, with no branching:
     *
     *   shipping = base + extra + multi × (qty − 1)
     *
     * `multi` is DERIVED — the 2-unit line minus the 1-unit line — rather than read
     * off the charge row. That is what makes the single formula correct in every
     * case: when the product has no multi-quantity option, the difference comes out
     * equal to `extra`, and the expression collapses back to `extra × qty` on its
     * own. There is no second code path for the client to get wrong.
     */
    public function index(string $slug): JsonResponse
    {
        $product = $this->products->findPublishedBySlug($slug);

        abort_if($product === null, 404);

        $product->loadMissing('shippingCharges');

        // Free-shipping product: every zone reports zero base + zero extras so the
        // storefront shows "Free" and the quantity-aware estimate stays at 0.
        $free = ! $product->shipping_charge_allowed;
        $zero = Money::fromMinor(0);

        $zones = ShippingZone::query()
            ->active()
            ->ordered()
            ->get()
            ->map(function (ShippingZone $z) use ($product, $free, $zero): array {
                $oneUnit = $product->extraMinorFor($z->id, 1);
                $twoUnits = $product->extraMinorFor($z->id, 2);

                return [
                    'id' => $z->id,
                    'name' => $z->name,
                    'base' => $this->money($free ? $zero : $z->cost),
                    'extra_per_unit' => $this->money($free ? $zero : Money::fromMinor($oneUnit)),
                    'multi_extra_per_unit' => $this->money(
                        $free ? $zero : Money::fromMinor(max(0, $twoUnits - $oneUnit)),
                    ),
                ];
            })
            ->all();

        return response()->json([
            'data' => $zones,
            'free_shipping' => $free,
            'multi_qty_enabled' => ! $free && (bool) $product->multi_qty_shipping_enabled,
        ]);
    }

    /**
     * @return array{minor:int, display:float, formatted:string}
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
