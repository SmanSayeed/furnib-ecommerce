<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\Order;
use App\Support\Money;
use App\Support\Orders\PayLink;

/**
 * Renders a `{placeholder}` template and builds the order's placeholder map —
 * reused by every channel (SMS now, email later) and any future promo, so
 * message-building lives in one place. Unknown placeholders resolve to blank.
 */
final class MessageTemplate
{
    /**
     * @param  array<string, string>  $vars
     */
    public static function render(string $template, array $vars): string
    {
        return preg_replace_callback(
            '/\{(\w+)\}/',
            static fn (array $m): string => $vars[$m[1]] ?? '',
            $template,
        ) ?? $template;
    }

    /**
     * Placeholder values for an order: {name} {order_no} {total} {due} {tracking}.
     *
     * @return array<string, string>
     */
    public static function forOrder(Order $order): array
    {
        $order->loadMissing(['customer', 'shipment']);

        $dueMinor = max(0, $order->total->toMinor() - $order->advance_paid->toMinor());

        return [
            'name' => (string) ($order->customer->name ?? 'গ্রাহক'),
            'order_no' => $order->order_no,
            'total' => $order->total->format('Tk '),
            'due' => Money::fromMinor($dueMinor)->format('Tk '),
            'tracking' => (string) ($order->shipment->tracking_code ?? ''),
            'pay_url' => PayLink::for($order),
        ];
    }
}
