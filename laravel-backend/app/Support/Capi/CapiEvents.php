<?php

declare(strict_types=1);

namespace App\Support\Capi;

use App\Models\Order;
use App\Models\Product;
use App\Support\Money;

/**
 * Builds {@see CapiEvent}s from domain objects. Money values are formatted as
 * Meta expects ("1234.50"); `content_ids` are always the product SKU so they
 * join cleanly with the product feed for dynamic/catalog ads.
 */
final class CapiEvents
{
    public const CURRENCY = 'BDT';

    /** Deterministic id so the browser Pixel and the server fire ONE Purchase. */
    public static function purchaseEventId(Order $order): string
    {
        return 'purchase.'.$order->order_no;
    }

    public static function purchase(Order $order, CapiUserData $user, ?string $url = null): CapiEvent
    {
        $order->loadMissing('items');

        $contents = $order->items->map(static fn ($item): array => [
            'id' => $item->sku !== '' ? $item->sku : (string) $item->product_id,
            'quantity' => (int) $item->qty,
            'item_price' => self::amount($item->price),
        ])->all();

        return new CapiEvent(
            eventName: 'Purchase',
            eventId: self::purchaseEventId($order),
            eventTime: time(),
            actionSource: 'website',
            eventSourceUrl: $url,
            userData: $user,
            customData: [
                'currency' => self::CURRENCY,
                'value' => self::amount($order->total),
                'content_type' => 'product',
                'content_ids' => array_column($contents, 'id'),
                'contents' => $contents,
                'num_items' => (int) $order->items->sum('qty'),
                'order_id' => $order->order_no,
            ],
        );
    }

    public static function viewContent(Product $product, int $qty, CapiUserData $user, string $eventId, ?string $url): CapiEvent
    {
        return self::product('ViewContent', $product, $qty, $user, $eventId, $url);
    }

    public static function initiateCheckout(Product $product, int $qty, CapiUserData $user, string $eventId, ?string $url): CapiEvent
    {
        return self::product('InitiateCheckout', $product, $qty, $user, $eventId, $url);
    }

    public static function lead(Product $product, int $qty, CapiUserData $user, string $eventId, ?string $url): CapiEvent
    {
        return self::product('Lead', $product, $qty, $user, $eventId, $url);
    }

    private static function product(string $event, Product $product, int $qty, CapiUserData $user, string $eventId, ?string $url): CapiEvent
    {
        $qty = max(1, $qty);
        $sku = $product->sku !== '' ? $product->sku : (string) $product->id;
        $unit = $product->discount_price ?? $product->price;

        return new CapiEvent(
            eventName: $event,
            eventId: $eventId,
            eventTime: time(),
            actionSource: 'website',
            eventSourceUrl: $url,
            userData: $user,
            customData: [
                'currency' => self::CURRENCY,
                'value' => self::amount(Money::fromMinor($unit->toMinor() * $qty)),
                'content_type' => 'product',
                'content_ids' => [$sku],
                'contents' => [[
                    'id' => $sku,
                    'quantity' => $qty,
                    'item_price' => self::amount($unit),
                ]],
            ],
        );
    }

    private static function amount(Money $money): string
    {
        return number_format($money->toDisplay(), 2, '.', '');
    }
}
