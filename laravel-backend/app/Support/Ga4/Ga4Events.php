<?php

declare(strict_types=1);

namespace App\Support\Ga4;

use App\Models\Order;
use App\Models\OrderItem;

/**
 * Builds {@see Ga4Event}s from domain objects, following the GA4 ecommerce
 * `purchase` spec (transaction_id + value + currency + items).
 */
final class Ga4Events
{
    public const CURRENCY = 'BDT';

    public static function purchase(Order $order, string $clientId): Ga4Event
    {
        $order->loadMissing('items.product.category');

        $items = $order->items->map(static fn (OrderItem $item): array => array_filter([
            'item_id' => $item->sku !== null && $item->sku !== '' ? $item->sku : (string) $item->product_id,
            'item_name' => $item->title,
            'item_category' => $item->product?->category?->title,
            'price' => $item->price->toDisplay(),
            'quantity' => (int) $item->qty,
        ], static fn (mixed $v): bool => $v !== null))->values()->all();

        return new Ga4Event(
            clientId: $clientId,
            name: 'purchase',
            params: [
                'transaction_id' => $order->order_no,
                'currency' => self::CURRENCY,
                'value' => $order->total->toDisplay(),
                'shipping' => $order->shipping_cost->toDisplay(),
                'items' => $items,
            ],
        );
    }
}
