<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only courier consignments list. Gated by `orders.view`. The encrypted
 * raw courier payload is never exposed.
 */
class ConsignmentController extends Controller
{
    public function index(): Response
    {
        $shipments = Shipment::query()
            ->with('order:id,order_no')
            ->latest('id')
            ->limit(300)
            ->get()
            ->map(fn (Shipment $s): array => [
                'id' => $s->id,
                'order_no' => $s->order?->order_no,
                'courier' => $s->courier,
                'consignment_id' => $s->consignment_id,
                'tracking_code' => $s->tracking_code,
                'status' => $s->status,
                'cod_amount' => $s->cod_amount->format('৳'),
                'at' => $s->created_at?->toDateTimeString(),
            ])
            ->all();

        return Inertia::render('shipping/consignments/index', ['shipments' => $shipments]);
    }
}
