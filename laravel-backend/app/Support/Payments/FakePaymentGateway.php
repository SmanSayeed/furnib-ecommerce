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

    /** @var array<string, array<string, mixed>|null> scripted queryTransaction() results, keyed by tran_id */
    public array $queries = [];

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
        // Currency defaults to BDT to mirror the real gateway, which always
        // returns one; a test can override it to simulate a wrong-currency fraud.
        return array_merge(
            ['currency' => 'BDT'],
            $this->nextValidation ?? ['status' => 'INVALID', 'val_id' => $valId],
        );
    }

    public function queryTransaction(string $tranId): ?array
    {
        // Not scripted → the gateway has no record yet (leave pending).
        return $this->queries[$tranId] ?? null;
    }

    /**
     * Script the next queryTransaction() result for a tran_id.
     *
     * @param  array<string, mixed>|null  $data
     */
    public function fakeQuery(string $tranId, ?array $data): void
    {
        $this->queries[$tranId] = $data;
    }

    /**
     * Tests exercise the real security gate (validatePayment), so the cheap
     * signature pre-check is a no-op here unless a test scripts otherwise.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(array $payload): bool
    {
        return $this->callbackValid;
    }

    public bool $callbackValid = true;

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
