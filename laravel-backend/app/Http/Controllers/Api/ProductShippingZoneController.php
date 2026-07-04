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
     * Active shipping zones for a product, each with the zone's base cost and
     * this product's per-unit extra, so the storefront can show the real,
     * quantity-aware shipping cost.
     */
    public function index(string $slug): JsonResponse
    {
        $product = $this->products->findPublishedBySlug($slug);

        abort_if($product === null, 404);

        $product->loadMissing('shippingCharges');

        // Free-shipping product: every zone reports zero base + zero extra so the
        // storefront shows "Free" and the quantity-aware estimate stays at 0.
        $free = ! $product->shipping_charge_allowed;
        $zero = Money::fromMinor(0);

        $zones = ShippingZone::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (ShippingZone $z): array => [
                'id' => $z->id,
                'name' => $z->name,
                'base' => $this->money($free ? $zero : $z->cost),
                'extra_per_unit' => $this->money(Money::fromMinor($product->extraPerUnitMinorFor($z->id))),
            ])
            ->all();

        return response()->json([
            'data' => $zones,
            'free_shipping' => $free,
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
