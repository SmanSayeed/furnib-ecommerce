<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Shipments\CreateConsignment;
use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Order;
use App\Support\Courier\CourierException;
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
        // Look up the courier first (any state) so we know which location fields to
        // validate; the `exists … is_active` rule below still rejects an inactive or
        // unknown courier with a proper validation error.
        $courier = Courier::query()->whereKey($request->integer('courier_id'))->first();

        $request->validate([
            'courier_id' => ['required', 'integer', Rule::exists('couriers', 'id')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:500'],
            ...($courier !== null ? $this->metaRules($courier) : []),
        ]);

        // Validation passed, so the courier exists and is active.
        /** @var Courier $courier */

        // An API courier must be configured before it can be booked; a manual
        // courier is always bookable (recorded only).
        if ($courier->isApi() && ! $this->couriers->canBookViaApi($courier)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __(':name is not configured yet. Add its API credentials first.', ['name' => $courier->name]),
            ]);

            return back();
        }

        $note = $request->filled('note') ? (string) $request->string('note') : null;

        // A provider rejection (bad key, duplicate invoice, blocked egress) used to
        // escape as an unhandled exception and hit the admin as a white 500 page —
        // with no way to tell WHICH of those it was. Surface the provider's own
        // message and keep the full trace in /admin/dev/errors.
        try {
            $createConsignment->handle($order, $courier, $note, $this->metaFor($courier, $request));
        } catch (CourierException $e) {
            report($e);
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

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

        try {
            $shipment->update(['status' => $driver->getStatus((string) $shipment->tracking_code)]);
        } catch (CourierException $e) {
            report($e);
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tracking status updated.')]);

        return back();
    }

    /**
     * Validation rules for the booking-time location fields a courier needs. RedX
     * needs a delivery area; Pathao needs a city/zone/area cascade. Others need
     * nothing.
     *
     * @return array<string, mixed>
     */
    private function metaRules(Courier $courier): array
    {
        return match ($courier->driver) {
            Courier::DRIVER_REDX => [
                'delivery_area_id' => ['required', 'integer'],
                'delivery_area' => ['required', 'string', 'max:255'],
            ],
            Courier::DRIVER_PATHAO => [
                'recipient_city' => ['required', 'integer'],
                'recipient_zone' => ['required', 'integer'],
                'recipient_area' => ['required', 'integer'],
            ],
            default => [],
        };
    }

    /**
     * The booking metadata to snapshot on the shipment, shaped per driver.
     *
     * @return array<string, mixed>
     */
    private function metaFor(Courier $courier, Request $request): array
    {
        return match ($courier->driver) {
            Courier::DRIVER_REDX => [
                'delivery_area_id' => $request->integer('delivery_area_id'),
                'delivery_area' => (string) $request->string('delivery_area'),
            ],
            Courier::DRIVER_PATHAO => [
                'recipient_city' => $request->integer('recipient_city'),
                'recipient_zone' => $request->integer('recipient_zone'),
                'recipient_area' => $request->integer('recipient_area'),
            ],
            default => [],
        };
    }
}
