<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Support\Courier\CascadesLocations;
use App\Support\Courier\CourierManager;
use App\Support\Courier\ListsDeliveryAreas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Booking-time location lookups for couriers that need them (RedX area, Pathao
 * city/zone/area cascade). The provider API is called server-side so credentials
 * never reach the browser; only the id/name option list is returned. A provider
 * error yields an empty list + a message rather than a 500 (booking degrades to
 * "try again" instead of breaking the order page).
 */
class CourierLocationController extends Controller
{
    public function __construct(private readonly CourierManager $couriers) {}

    /** RedX delivery areas. */
    public function areas(Courier $courier): JsonResponse
    {
        $driver = $this->couriers->driverFor($courier);

        if (! $driver instanceof ListsDeliveryAreas) {
            return response()->json(['options' => []]);
        }

        return $this->guard(fn (): array => $driver->areas());
    }

    /** Pathao cities. */
    public function cities(Courier $courier): JsonResponse
    {
        $driver = $this->couriers->driverFor($courier);

        if (! $driver instanceof CascadesLocations) {
            return response()->json(['options' => []]);
        }

        return $this->guard(fn (): array => $driver->cities());
    }

    /** Pathao zones for a city. */
    public function zones(Request $request, Courier $courier): JsonResponse
    {
        $driver = $this->couriers->driverFor($courier);
        $cityId = $request->integer('city_id');

        if (! $driver instanceof CascadesLocations || $cityId <= 0) {
            return response()->json(['options' => []]);
        }

        return $this->guard(fn (): array => $driver->zones($cityId));
    }

    /** Pathao areas for a zone. */
    public function pathaoAreas(Request $request, Courier $courier): JsonResponse
    {
        $driver = $this->couriers->driverFor($courier);
        $zoneId = $request->integer('zone_id');

        if (! $driver instanceof CascadesLocations || $zoneId <= 0) {
            return response()->json(['options' => []]);
        }

        return $this->guard(fn (): array => $driver->areas($zoneId));
    }

    /**
     * @param  callable(): array<int, array{id: int, name: string}>  $fetch
     */
    private function guard(callable $fetch): JsonResponse
    {
        try {
            return response()->json(['options' => $fetch()]);
        } catch (Throwable) {
            return response()->json([
                'options' => [],
                'error' => __('Could not load courier locations. Check the courier credentials and try again.'),
            ], 200);
        }
    }
}
