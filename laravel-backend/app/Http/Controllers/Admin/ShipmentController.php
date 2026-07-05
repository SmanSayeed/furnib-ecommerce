<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Shipments\CreateConsignment;
use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Order;
use App\Support\Courier\CourierManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Admin courier actions. Booking and tracking a consignment are management
 * actions guarded by the `orders.manage` permission (applied on the route).
 */
class ShipmentController extends Controller
{
    public function __construct(private readonly CourierManager $couriers) {}

    public function store(Request $request, Order $order, CreateConsignment $createConsignment): RedirectResponse
    {
        $validated = $request->validate([
            'courier_id' => ['required', 'integer', Rule::exists('couriers', 'id')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $courier = Courier::query()->whereKey($validated['courier_id'])->firstOrFail();

        // An API courier must be configured before it can be booked; a manual
        // courier is always bookable (recorded only).
        if ($courier->isApi() && ! $this->couriers->canBookViaApi($courier)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __(':name is not configured yet. Add its API credentials first.', ['name' => $courier->name]),
            ]);

            return back();
        }

        $createConsignment->handle($order, $courier, $validated['note'] ?? null);

        $message = $courier->isApi()
            ? __('Consignment booked with :name.', ['name' => $courier->name])
            : __('Shipment recorded for :name (book it manually).', ['name' => $courier->name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    public function track(Order $order): RedirectResponse
    {
        $shipment = $order->shipment;
        $courier = $shipment?->courierModel;

        if ($shipment === null || blank($shipment->tracking_code) || $courier === null) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => __('Nothing to track for this order.')]);

            return back();
        }

        $driver = $this->couriers->driverFor($courier);

        if ($driver === null) {
            // Manual courier — status is updated by hand, not via an API.
            Inertia::flash('toast', ['type' => 'warning', 'message' => __('This courier has no API — update the status manually.')]);

            return back();
        }

        $shipment->update(['status' => $driver->getStatus((string) $shipment->tracking_code)]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tracking status updated.')]);

        return back();
    }
}
