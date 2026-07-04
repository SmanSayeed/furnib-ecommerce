<?php

declare(strict_types=1);

namespace App\Support\Orders;

use App\Models\Order;

/**
 * Builds and verifies the customer's self-service payment link
 * (`{frontend}/pay/{order_no}?t={token}`). The token is an HMAC of the order_no
 * keyed by the app secret, so the link is unguessable and cannot be enumerated —
 * order details + payment are only exposed to whoever holds the correct token
 * (i.e. the customer who received the SMS). No IDOR.
 */
final class PayLink
{
    public static function for(Order $order): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return $base.'/pay/'.$order->order_no.'?t='.self::token($order->order_no);
    }

    public static function token(string $orderNo): string
    {
        return hash_hmac('sha256', 'pay:'.$orderNo, (string) config('app.key'));
    }

    public static function verify(string $orderNo, string $token): bool
    {
        return $token !== '' && hash_equals(self::token($orderNo), $token);
    }
}
