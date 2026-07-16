<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Models\Order;
use App\Services\Orders\RecalculateOrderTotals;
use App\Services\Payments\OrderPaymentReconciler;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manually override an existing order's delivery charge.
 *
 * Shipping is normally derived from the zone + per-product rules, but a specific
 * order sometimes needs a hand-set figure (a negotiated free delivery, a remote-
 * area surcharge, a correction). Because it moves the total — hence the due and
 * what the pay link + invoice charge — it is guarded on exactly the same edges as
 * a discount: a paid order, an already-booked order, and a total that would drop
 * below what the customer has already paid.
 *
 * Note: a later delivery-zone change re-derives shipping from the zone and would
 * replace this override — the override is a point-in-time correction, not a lock.
 */
final class UpdateOrderShipping
{
    public function __construct(
        private readonly OrderPaymentReconciler $reconciler,
    ) {}

    public function handle(Order $order, int $shippingMinor): void
    {
        $shippingMinor = max(0, $shippingMinor);

        // A booked order's COD was snapshotted with the courier at booking; we
        // cannot quietly change the cash the rider collects.
        if ($order->shipment !== null) {
            throw ValidationException::withMessages([
                'shipping_cost' => 'This order is already booked with a courier, which holds the old amount. Cancel and re-book the consignment before changing the delivery charge.',
            ]);
        }

        // Changing the total of a settled order silently creates a debt or a refund.
        if ($order->payment_status === 'paid') {
            throw ValidationException::withMessages([
                'shipping_cost' => 'This order is already paid — changing the delivery charge would change the total. Record a refund or a payment instead.',
            ]);
        }

        DB::transaction(function () use ($order, $shippingMinor): void {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            $locked->shipping_cost = Money::fromMinor($shippingMinor);
            $newTotalMinor = RecalculateOrderTotals::totalMinor($locked);

            // Never let the new total fall below what the customer has already paid
            // — that owes them money, a deliberate refund decision.
            if ($newTotalMinor < $locked->advance_paid->toMinor()) {
                throw ValidationException::withMessages([
                    'shipping_cost' => 'That delivery charge would drop the total below what the customer has already paid ('.$locked->advance_paid->format('৳').'). Record a refund first.',
                ]);
            }

            $locked->total = Money::fromMinor($newTotalMinor);
            $locked->save();

            // The total moved, so paid / partial / unpaid + due may have moved. The
            // pay link and invoice read the order row, so they follow for free.
            $this->reconciler->reconcile($locked);
        });
    }
}
