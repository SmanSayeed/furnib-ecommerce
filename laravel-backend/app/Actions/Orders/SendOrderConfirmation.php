<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Support\Sms\SmsGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Notifies the customer that their order was received: an SMS to the mobile
 * (always) and a queued email when an address is on file. Delivery failures are
 * non-fatal — the order has already been placed, so we only log and move on.
 */
final class SendOrderConfirmation
{
    public function __construct(private readonly SmsGateway $sms) {}

    public function handle(Order $order): void
    {
        $order->loadMissing('customer');

        $this->sendSms($order);
        $this->sendEmail($order);
    }

    private function sendSms(Order $order): void
    {
        $mobile = $order->customer?->mobile;

        if (blank($mobile)) {
            return;
        }

        try {
            $this->sms->send(
                (string) $mobile,
                "Furnib: order {$order->order_no} received. Total {$order->total->format()}. Thank you!",
            );
        } catch (Throwable $e) {
            Log::warning('Order confirmation SMS failed', [
                'order_no' => $order->order_no,
                'error' => $e->getMessage(),
            ]);
        }
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
