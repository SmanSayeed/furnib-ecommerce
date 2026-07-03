<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Order;
use App\Support\Money;

/**
 * Payment gateway abstraction. Credentials live in encrypted settings and never
 * leave the server. Calling code depends only on this interface; the concrete
 * SSLCommerz implementation is faked in tests.
 */
interface PaymentGateway
{
    /**
     * Create a hosted payment session for the order and return the gateway
     * redirect URL the customer's browser should be sent to.
     */
    public function initSession(Order $order, Money $amount, string $tranId): string;

    /**
     * Verify a transaction SERVER-SIDE using the gateway's validation API and
     * the secret store credentials. The redirect/IPN POST is never trusted on
     * its own. Returns a normalized array:
     * ['status' => string, 'tran_id' => string, 'amount' => float (taka),
     *  'currency' => string, 'val_id' => string].
     *
     * @return array<string, mixed>
     */
    public function validatePayment(string $valId): array;

    /**
     * Cheap authenticity check on a callback/IPN payload BEFORE any outbound
     * call — proves the POST really came from the gateway (e.g. SSLCommerz'
     * verify_sign hash). Returns true when the signature is valid OR when the
     * gateway sent no signature fields (in which case the authoritative
     * validatePayment() call remains the real gate). Returns false only when a
     * signature is present but does not match.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(array $payload): bool;
}
