<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Services\Payments\OrderPaymentReconciler;
use App\Services\Shipping\ShippingCalculator;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Correct an order's customer, delivery address and shipping zone.
 *
 * The sharp edge is the zone: changing it changes the shipping cost, hence the
 * total, hence the payable amount on the pay link and the invoice. So a zone
 * change is a MONEY change and is guarded accordingly — while a plain address or
 * name fix touches no money at all and is always allowed.
 */
final class UpdateOrderCustomer
{
    public function __construct(
        private readonly ShippingCalculator $shipping,
        private readonly OrderPaymentReconciler $reconciler,
    ) {}

    /**
     * @param  array{name: ?string, email: ?string, mobile: string, address: string, shipping_zone_id: ?int}  $data
     * @return bool whether a booked consignment now holds a stale address
     */
    public function handle(Order $order, array $data): bool
    {
        $newZoneId = $data['shipping_zone_id'] ?? null;
        $zoneChanged = $newZoneId !== $order->shipping_zone_id;

        if ($zoneChanged) {
            $this->guardZoneChange($order);
        }

        DB::transaction(function () use ($order, $data, $newZoneId, $zoneChanged): void {
            // The customer row is SHARED across every order this person placed, so
            // this corrects them everywhere. That is intended — it is the same human.
            $order->customer?->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'], // already normalized by the FormRequest
            ]);

            $order->update([
                'address' => $data['address'],
                'shipping_zone_id' => $newZoneId,
            ]);

            if ($zoneChanged) {
                $this->recomputeShipping($order, $newZoneId);
            }
        });

        // The consignment snapshotted the recipient address (and the COD amount) at
        // booking time — the courier still has the OLD one. We cannot silently fix
        // that, so we tell the admin to cancel and re-book.
        return $order->shipment !== null;
    }

    /**
     * A zone change moves the total. Refuse the two cases where that would quietly
     * create a debt or a refund obligation.
     */
    private function guardZoneChange(Order $order): void
    {
        if ($order->payment_status === 'paid') {
            throw ValidationException::withMessages([
                'shipping_zone_id' => 'This order is already paid — changing the delivery zone would change the total. Record a refund or a payment instead.',
            ]);
        }
    }

    private function recomputeShipping(Order $order, ?int $zoneId): void
    {
        $zone = $zoneId === null
            ? null
            : ShippingZone::query()->active()->find($zoneId);

        $products = Product::query()
            ->whereIn('id', $order->items->pluck('product_id')->filter()->all())
            ->with('shippingCharges')
            ->get()
            ->keyBy('id');

        $lines = $order->items
            ->map(fn ($item): ?array => ($product = $products->get($item->product_id)) === null
                ? null                          // product deleted since — it can no longer carry a charge
                : ['product' => $product, 'qty' => (int) $item->qty])
            ->filter()
            ->values();

        $shippingMinor = $this->shipping->minorFor($lines, $zone);
        $totalMinor = $order->subtotal->toMinor() + $shippingMinor;

        // Never let a recompute leave the customer owed money without an explicit
        // refund decision — that is a different, deliberate action.
        if ($totalMinor < $order->advance_paid->toMinor()) {
            throw ValidationException::withMessages([
                'shipping_zone_id' => 'That zone would drop the total below what the customer has already paid. Record a refund first.',
            ]);
        }

        $order->update([
            'shipping_cost' => Money::fromMinor($shippingMinor),
            'total' => Money::fromMinor($totalMinor),
        ]);

        // The total moved, so "paid / partial / unpaid" may have moved with it.
        // The pay link and the invoice read the order row, so they follow for free.
        $this->reconciler->reconcile($order);
    }
}
