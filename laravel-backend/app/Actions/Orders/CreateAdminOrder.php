<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Payments\RecordManualPayment;
use App\DTOs\PlaceOrderData;
use App\Enums\OrderNotificationEvent;
use App\Jobs\SendOrderNotification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;

/**
 * Creates an order on a customer's behalf from the admin panel.
 *
 * It reuses the exact placement engine the storefront uses (`PlaceOrder`) — same
 * stock check + decrement, same shipping calculator, same money invariant — but
 * with `source = admin`, which unlocks the staff-only levers: a per-line unit
 * price override, an order-level discount, and a manual shipping figure. Those
 * levers are gated inside `PlaceOrder` itself, so a storefront payload can never
 * reach them.
 *
 * Payment is not automatic here: if the admin collected something up front it is
 * recorded as a manual ledger credit, which drives the paid/partial/unpaid state
 * through the same reconciler as every other payment.
 */
final class CreateAdminOrder
{
    public function __construct(
        private readonly PlaceOrder $placeOrder,
        private readonly RecordManualPayment $recordManual,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the request-validated payload (untyped);
     *                                      every field is cast defensively below.
     */
    public function handle(array $data, User $actor): Order
    {
        $items = [];
        foreach ((array) ($data['items'] ?? []) as $raw) {
            $i = (array) $raw;
            $line = ['product_id' => (int) ($i['product_id'] ?? 0), 'qty' => (int) ($i['qty'] ?? 1)];

            // A blank/absent unit price means "use the product's effective price".
            if (isset($i['unit_price_minor'])) {
                $line['price_override'] = (int) $i['unit_price_minor'];
            }

            $items[] = $line;
        }

        $customer = (array) ($data['customer'] ?? []);
        $intOrNull = static fn (string $key): ?int => isset($data[$key]) ? (int) $data[$key] : null;

        $dto = new PlaceOrderData(
            items: $items,
            customerMobile: (string) ($customer['mobile'] ?? ''),
            customerName: isset($customer['name']) ? (string) $customer['name'] : null,
            customerEmail: isset($customer['email']) ? (string) $customer['email'] : null,
            shippingZoneId: $intOrNull('shipping_zone_id'),
            address: (string) ($data['address'] ?? ''),
            ip: null,
            userAgent: 'admin',
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            source: 'admin',
            createdBy: $actor->id,
            discountMinor: $intOrNull('discount_minor'),
            discountNote: isset($data['discount_note']) ? (string) $data['discount_note'] : null,
            shippingOverrideMinor: $intOrNull('shipping_override_minor'),
        );

        $order = $this->placeOrder->handle($dto);

        // Whatever the admin says was collected up front becomes a manual ledger
        // credit — capped at the total — so the payment state is derived, never set.
        $advanceMinor = min((int) ($data['advance_paid_minor'] ?? 0), $order->total->toMinor());

        if ($advanceMinor > 0) {
            $this->recordManual->handle(
                $order,
                Payment::DIRECTION_CREDIT,
                Money::fromMinor($advanceMinor),
                'Advance collected at admin order creation.',
            );
        }

        // Optional immediate confirm — updated via the model so the OrderObserver
        // runs (auto-book the courier, fire the confirmed notification if enabled),
        // exactly as confirming from the order detail page would.
        if (filter_var($data['confirm'] ?? false, FILTER_VALIDATE_BOOL)) {
            $order->update(['status' => 'confirmed']);
        }

        // Optional pay-link SMS (the storefront "placed" message). The order is
        // fresh, so nothing is deduped; it re-renders the live total + pay link.
        if (filter_var($data['send_sms'] ?? false, FILTER_VALIDATE_BOOL)) {
            SendOrderNotification::dispatch($order->id, OrderNotificationEvent::Placed->value);
        }

        return $order->refresh()->load('items');
    }
}
