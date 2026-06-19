<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Shipments\CreateConsignment;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Courier\CourierGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Admin courier actions. Booking and tracking a consignment are management
 * actions guarded by the `orders.manage` permission (applied on the route).
 */
class ShipmentController extends Controller
{
    public function __construct(private readonly CourierGateway $courier) {}

    public function store(Request $request, Order $order, CreateConsignment $createConsignment): RedirectResponse
    {
        $createConsignment->handle($order, $request->string('note')->toString() ?: null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Consignment booked with the courier.')]);

        return back();
    }

    public function track(Order $order): RedirectResponse
    {
        $shipment = $order->shipment;

        if ($shipment !== null && filled($shipment->tracking_code)) {
            $shipment->update(['status' => $this->courier->getStatus((string) $shipment->tracking_code)]);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Tracking status updated.')]);
        }

        return back();
    }
}
