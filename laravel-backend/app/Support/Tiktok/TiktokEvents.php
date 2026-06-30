<?php

declare(strict_types=1);

namespace App\Support\Tiktok;

use App\Models\Order;
use App\Models\Product;
use App\Support\Money;

/**
 * Builds {@see TiktokEvent}s from domain objects. `content_id` is always the
 * product SKU so it joins cleanly with the TikTok product catalog. Event names
 * follow TikTok's standard set (CompletePayment = purchase).
 */
final class TiktokEvents
{
    public const CURRENCY = 'BDT';

    /** Maps our GA4-style funnel name to the TikTok standard event name. */
    public const NAMES = [
        'ViewContent' => 'ViewContent',
        'InitiateCheckout' => 'InitiateCheckout',
        'Lead' => 'Contact',
    ];

    /** Deterministic id so the browser Pixel and the server fire ONE purchase. */
    public static function purchaseEventId(Order $order): string
    {
        return 'purchase.'.$order->order_no;
    }

    public static function purchase(Order $order, TiktokUserData $user, ?string $url = null): TiktokEvent
    {
        $order->loadMissing('items');

        $contents = $order->items->map(static fn ($item): array => [
            'content_id' => $item->sku !== null && $item->sku !== '' ? $item->sku : (string) $item->product_id,
            'content_type' => 'product',
            'content_name' => $item->title,
            'price' => $item->price->toDisplay(),
            'quantity' => (int) $item->qty,
        ])->all();

        return new TiktokEvent(
            eventName: 'CompletePayment',
            eventId: self::purchaseEventId($order),
            eventTime: time(),
            eventSourceUrl: $url,
            userData: $user,
            properties: [
                'currency' => self::CURRENCY,
                'value' => $order->total->toDisplay(),
                'content_type' => 'product',
                'contents' => $contents,
            ],
        );
    }

    /**
     * A funnel event for a single product. `$metaName` is the Meta/funnel name
     * (ViewContent / InitiateCheckout / Lead); it is mapped to TikTok's name.
     */
    public static function product(string $metaName, Product $product, int $qty, TiktokUserData $user, string $eventId, ?string $url): TiktokEvent
    {
        $qty = max(1, $qty);
        $sku = $product->sku !== '' ? $product->sku : (string) $product->id;
        $unit = $product->discount_price ?? $product->price;

        return new TiktokEvent(
            eventName: self::NAMES[$metaName] ?? $metaName,
            eventId: $eventId,
            eventTime: time(),
            eventSourceUrl: $url,
            userData: $user,
            properties: [
                'currency' => self::CURRENCY,
                'value' => Money::fromMinor($unit->toMinor() * $qty)->toDisplay(),
                'content_type' => 'product',
                'contents' => [[
                    'content_id' => $sku,
                    'content_type' => 'product',
                    'content_name' => $product->title,
                    'price' => $unit->toDisplay(),
                    'quantity' => $qty,
                ]],
            ],
        );
    }
}
