<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Money;
use App\Support\Orders\PayLink;
use App\Support\Payments\PayableState;
use App\Support\Payments\PaymentHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only order summary for the self-service pay page (`/pay/{order_no}`). The
 * signed `t` token gates access, so a shopper can only see + pay THEIR own order
 * (no IDOR / PII enumeration). Returns only display fields — never secrets. The
 * charge amounts are resolved server-side by the payment init endpoint, so
 * nothing here can be tampered into a wrong price.
 */
class PayPageController extends Controller
{
    public function summary(Request $request, string $order_no): JsonResponse
    {
        $token = (string) $request->query('t', '');

        // Constant-time token check first; a bad/absent token is a generic 404 so
        // orders cannot be probed by id.
        if (! PayLink::verify($order_no, $token)) {
            abort(404);
        }

        $order = Order::query()
            ->where('order_no', $order_no)
            ->with(['items', 'customer', 'payments'])
            ->firstOrFail();

        $state = PayableState::for($order);

        return response()->json([
            'data' => [
                'order_no' => $order->order_no,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'customer_name' => $order->customer->name,
                'address' => $order->address,
                'items' => $order->items->map(fn ($i): array => [
                    'title' => $i->title,
                    'qty' => $i->qty,
                    'price' => $i->price->format('Tk '),
                    'line_total' => $i->line_total->format('Tk '),
                ])->all(),
                'subtotal' => $order->subtotal->format('Tk '),
                'shipping' => $order->shipping_cost->format('Tk '),
                'total' => $order->total->format('Tk '),
                'advance_paid' => $order->advance_paid->format('Tk '),
                'due' => Money::fromMinor($state['due_minor'])->format('Tk '),
                // Which buttons the page should offer (shared PayableState rules).
                'can_pay_shipping' => $state['can_pay_shipping'],
                'can_pay_full' => $state['can_pay_full'],
                'shipping_minor' => $state['shipping_minor'],
                'due_minor' => $state['due_minor'],
                'payments' => PaymentHistory::forOrder($order),
            ],
        ]);
    }
}
