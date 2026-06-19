<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;

/**
 * Generates a unique, human-readable order number: `FNB-YYYYMMDD-XXXX`.
 */
final class OrderNumber
{
    public static function generate(): string
    {
        $date = now()->format('Ymd');

        do {
            $candidate = sprintf('FNB-%s-%04d', $date, random_int(0, 9999));
        } while (Order::withTrashed()->where('order_no', $candidate)->exists());

        return $candidate;
    }

    public static function matchesFormat(string $value): bool
    {
        return (bool) preg_match('/^FNB-\d{8}-\d{4}$/', $value);
    }
}
