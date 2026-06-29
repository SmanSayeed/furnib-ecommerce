<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Capi\CapiUserData;

/**
 * Builds the marketer's GA4/Meta dataLayer payload for an order — the rich
 * `ecommerce` + `user_data` + `order_info` block shared by the storefront
 * `place_order` push and the admin-confirm `purchase` push.
 *
 * `user_data` carries BOTH the raw fields the marketer asked for (name, phone,
 * address, area) AND their SHA-256 Meta-normalized hashes, plus the first-party
 * fbp/fbc cookies and the customer's IP. The raw values belong to the customer
 * whose order this is and only ever reach that customer's browser (success page)
 * or the authenticated admin's browser (order page) — never a third party. The
 * server-side Meta CAPI copy continues to send hashed PII only.
 *
 * The `event` name and `event_id` are NOT included here; the caller adds them
 * (`place_order.<order_no>` / `purchase.<order_no>`).
 */
final class OrderTrackingPayload
{
    /**
     * @return array{ecommerce: array<string, mixed>, user_data: array<string, mixed>, order_info: array<string, mixed>}
     */
    public static function for(Order $order): array
    {
        $order->loadMissing(['items.product.category', 'customer', 'shippingZone']);

        $method = self::paymentMethod($order);
        $itemCount = (int) $order->items->sum('qty');

        return [
            'ecommerce' => [
                'transaction_id' => $order->order_no,
                'value' => $order->total->toDisplay(),
                'tax' => 0,
                'shipping' => $order->shipping_cost->toDisplay(),
                'currency' => 'BDT',
                'coupon' => null,
                'payment_method' => $method,
                'items' => $order->items->map(static fn (OrderItem $item): array => [
                    'item_id' => $item->sku !== null && $item->sku !== '' ? $item->sku : (string) $item->product_id,
                    'item_name' => $item->title,
                    'price' => $item->price->toDisplay(),
                    'quantity' => $item->qty,
                    'item_category' => $item->product?->category?->title,
                ])->values()->all(),
            ],
            'user_data' => [
                'customer_id' => $order->customer_id,
                'name' => $order->customer?->name,
                'phone' => $order->customer?->mobile,
                'address' => $order->address,
                'area' => $order->shippingZone?->name,
                'hashed_name' => CapiUserData::hashName($order->customer?->name),
                'hashed_phone' => CapiUserData::hashPhone($order->customer?->mobile),
                'hashed_email' => CapiUserData::hashEmail($order->customer?->email),
                'fbp' => $order->fbp,
                'fbc' => $order->fbc,
                'client_ip' => $order->customer_ip,
            ],
            'order_info' => [
                'invoice_id' => $order->order_no,
                'order_id' => $order->order_no,
                'payment_method' => $method,
                'payment_status' => $order->payment_status,
                'grand_total' => $order->total->toDisplay(),
                'shipping' => $order->shipping_cost->toDisplay(),
                'discount' => 0,
                'coupon' => null,
                'item_count' => $itemCount,
            ],
        ];
    }

    /**
     * Best-effort method: an unpaid order is collected cash-on-delivery; any
     * recorded (partial/paid) payment means the customer paid online.
     */
    private static function paymentMethod(Order $order): string
    {
        return $order->payment_status === 'unpaid' ? 'cod' : 'online';
    }
}
