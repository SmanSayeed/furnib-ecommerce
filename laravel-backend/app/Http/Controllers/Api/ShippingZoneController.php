<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use Illuminate\Http\JsonResponse;

class ShippingZoneController extends Controller
{
    /**
     * Active shipping zones for the storefront checkout selector.
     */
    public function index(): JsonResponse
    {
        $zones = ShippingZone::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (ShippingZone $z): array => [
                'id' => $z->id,
                'name' => $z->name,
                'cost' => [
                    'minor' => $z->cost->toMinor(),
                    'display' => $z->cost->toDisplay(),
                    'formatted' => $z->cost->format(),
                ],
            ])
            ->all();

        return response()->json(['data' => $zones]);
    }
}
