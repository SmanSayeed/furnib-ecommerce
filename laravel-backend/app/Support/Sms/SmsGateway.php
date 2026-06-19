<?php

declare(strict_types=1);

namespace App\Support\Sms;

/**
 * Provider-agnostic SMS abstraction. Calling code depends only on this
 * interface; the concrete driver (log now, a concrete BD provider later) is
 * resolved from the container. Credentials live in encrypted settings — never
 * in the repo or the client bundle.
 */
interface SmsGateway
{
    /**
     * Send a single SMS. Returns true on accepted-by-gateway, false on a
     * handled failure. Implementations must not throw for ordinary delivery
     * failures (callers decide whether a failure is fatal).
     *
     * @param  string  $mobile  Canonical E.164 destination (e.g. +8801XXXXXXXXX).
     */
    public function send(string $mobile, string $message): bool;
}
