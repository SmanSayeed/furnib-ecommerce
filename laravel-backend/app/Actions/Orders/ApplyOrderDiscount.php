<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\User;
use App\Services\Orders\RecalculateOrderTotals;
use App\Services\Payments\OrderPaymentReconciler;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies (or clears) an order-level discount an admin grants after placement.
 *
 * A discount is a MONEY change: it moves the total, hence the due, hence the
 * amount the pay link and invoice charge. So it is guarded on every edge where a
 * silent total change would create a debt, a refund obligation, or a mismatch
 * with a courier that has already snapshotted the COD.
 */
final class ApplyOrderDiscount
{
    public function __construct(
        private readonly OrderPaymentReconciler $reconciler,
    ) {}

    /**
     * @param  int  $discountMinor  the new order-level discount in paisa (0 clears it)
     */
    public function handle(Order $order, int $discountMinor, ?string $note, User $actor): void
    {
        $discountMinor = max(0, $discountMinor);

        // A booked order's COD was snapshotted with the courier at booking. We
        // cannot quietly change the cash the rider collects.
        if ($order->shipment !== null) {
            throw ValidationException::withMessages([
                'discount' => 'This order is already booked with a courier, which holds the old amount. Cancel and re-book the consignment before changing the total.',
            ]);
        }

        // Reducing the total after full payment is a refund, not a discount.
        if ($order->payment_status === 'paid') {
            throw ValidationException::withMessages([
                'discount' => 'This order is already paid — record a refund instead of a discount.',
            ]);
        }

        // A discount reduces the goods price, never below zero.
        if ($discountMinor > $order->subtotal->toMinor()) {
            throw ValidationException::withMessages([
                'discount' => 'The discount cannot be more than the order subtotal ('.$order->subtotal->format('৳').').',
            ]);
        }

        DB::transaction(function () use ($order, $discountMinor, $note, $actor): void {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            $locked->discount = Money::fromMinor($discountMinor);
            $locked->discount_note = $discountMinor > 0 ? $note : null;
            $locked->discount_by = $discountMinor > 0 ? $actor->id : null;

            $newTotalMinor = RecalculateOrderTotals::totalMinor($locked);

            // Never let a discount drop the total below what the customer has
            // already paid — that owes them money, a deliberate refund decision.
            if ($newTotalMinor < $locked->advance_paid->toMinor()) {
                throw ValidationException::withMessages([
                    'discount' => 'That discount would drop the total below what the customer has already paid ('.$locked->advance_paid->format('৳').'). Record a refund first.',
                ]);
            }

            $locked->total = Money::fromMinor($newTotalMinor);
            $locked->save();

            // The total moved, so paid / partial / unpaid + due may have moved.
            // The pay link and invoice read the order row, so they follow for free.
            $this->reconciler->reconcile($locked);
        });
    }
}
