<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Order;
use App\Support\Money;

/**
 * In-memory payment gateway for tests. Records init sessions and returns a
 * scripted validation result so we can simulate a genuine VALID payment, a
 * forged/redirect-only success (gateway says INVALID), or an amount mismatch —
 * without any network call.
 */
final class FakePaymentGateway implements PaymentGateway
{
    /** @var array<int, array{order_id: int, amount: int, tran_id: string}> */
    public array $sessions = [];

    /** @var array<string, mixed>|null */
    public ?array $nextValidation = null;

    public function initSession(Order $order, Money $amount, string $tranId): string
    {
        $this->sessions[] = [
            'order_id' => $order->id,
            'amount' => $amount->toMinor(),
            'tran_id' => $tranId,
        ];

        return 'https://sandbox.sslcommerz.test/pay/'.$tranId;
    }

    public function validatePayment(string $valId): array
    {
        // Default: gateway reports the transaction as not valid (forged success).
        return $this->nextValidation ?? ['status' => 'INVALID', 'val_id' => $valId];
    }

    /**
     * Script the next validatePayment() result.
     *
     * @param  array<string, mixed>  $data
     */
    public function fakeValidation(array $data): void
    {
        $this->nextValidation = $data;
    }
}
