<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Support\MobileNumber;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Storefront "what do I still owe?" lookup for a just-placed order. Guarded by
 * order_no + the customer's own mobile so it can't be walked by guessing order
 * numbers (no IDOR). Read-only, rate-limited, returns only non-sensitive money
 * fields — never customer PII, address, or gateway data.
 */
final class OrderStatusController
{
    public function show(Request $request, string $orderNo): JsonResponse
    {
        $mobile = trim((string) $request->input('mobile', ''));

        // Normalize to canonical BD E.164 the same way orders are stored. An
        // invalid number simply can't match anything — answer a generic 404 so
        // we never reveal whether an order number exists.
        try {
            $normalized = MobileNumber::normalize($mobile);
        } catch (Throwable) {
            return $this->notFound();
        }

        $order = Order::query()
            ->where('order_no', $orderNo)
            ->whereHas('customer', fn ($q) => $q->where('mobile', $normalized))
            ->first();

        if ($order === null) {
            return $this->notFound();
        }

        $dueMinor = max(0, $order->total->toMinor() - $order->advance_paid->toMinor());

        return response()->json([
            'data' => [
                'order_no' => $order->order_no,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total' => $this->money($order->total),
                'advance_amount' => $this->money($order->advance_amount),
                'advance_paid' => $this->money($order->advance_paid),
                'due' => $this->money(Money::fromMinor($dueMinor)),
                'advance_required' => $order->advance_amount->toMinor() > 0,
            ],
        ]);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(
            ['error' => ['code' => 'not_found', 'message' => 'Order not found.']],
            404,
        );
    }

    /**
     * @return array{minor: int, display: float, formatted: string}
     */
    private function money(Money $money): array
    {
        return [
            'minor' => $money->toMinor(),
            'display' => $money->toDisplay(),
            'formatted' => $money->format(),
        ];
    }
}
