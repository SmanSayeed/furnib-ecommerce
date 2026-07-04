<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails the customer that their order was received (when an address is on file).
 * The order-placed SMS is handled separately by the notification system
 * (OrderNotificationEvent::Placed) so the customer gets exactly ONE SMS — this
 * action no longer sends SMS, avoiding a duplicate charge. Failures are
 * non-fatal: the order is already placed, so we log and move on.
 */
final class SendOrderConfirmation
{
    public function handle(Order $order): void
    {
        $order->loadMissing('customer');

        $this->sendEmail($order);
    }

    private function sendEmail(Order $order): void
    {
        $email = $order->customer?->email;

        if (blank($email)) {
            return;
        }

        try {
            Mail::to((string) $email)->queue(new OrderConfirmationMail($order));
        } catch (Throwable $e) {
            Log::warning('Order confirmation mail failed', [
                'order_no' => $order->order_no,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
