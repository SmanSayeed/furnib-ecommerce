<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShippingZoneFormRequest;
use App\Models\ShippingZone;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ShippingZoneController extends Controller
{
    public function index(): Response
    {
        $zones = ShippingZone::query()
            ->ordered()
            ->get()
            ->map(fn (ShippingZone $z): array => [
                'id' => $z->id,
                'name' => $z->name,
                'cost' => $z->cost->format(),
                'status' => $z->status,
                'position_order' => $z->position_order,
            ])
            ->all();

        return Inertia::render('shipping/zones/index', ['zones' => $zones]);
    }

    public function create(): Response
    {
        return Inertia::render('shipping/zones/form', ['zone' => null]);
    }

    public function store(ShippingZoneFormRequest $request): RedirectResponse
    {
        ShippingZone::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shipping zone created.')]);

        return to_route('admin.shipping-zones.index');
    }

    public function edit(ShippingZone $shippingZone): Response
    {
        return Inertia::render('shipping/zones/form', [
            'zone' => [
                'id' => $shippingZone->id,
                'name' => $shippingZone->name,
                'cost' => $shippingZone->cost->toDisplay(),
                'status' => $shippingZone->status,
                'position_order' => $shippingZone->position_order,
            ],
        ]);
    }

    public function update(ShippingZoneFormRequest $request, ShippingZone $shippingZone): RedirectResponse
    {
        $shippingZone->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shipping zone updated.')]);

        return to_route('admin.shipping-zones.index');
    }

    public function destroy(ShippingZone $shippingZone): RedirectResponse
    {
        $shippingZone->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shipping zone deleted.')]);

        return to_route('admin.shipping-zones.index');
    }
}
