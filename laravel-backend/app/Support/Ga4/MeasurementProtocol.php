<?php

declare(strict_types=1);

namespace App\Support\Ga4;

/**
 * GA4 Measurement Protocol abstraction. The API secret is a server-side secret
 * read from encrypted settings and never returned to the client. Faked in tests.
 */
interface MeasurementProtocol
{
    /**
     * Send one server-side event to GA4. Returns false when the integration is
     * not configured or the send was not accepted.
     */
    public function send(Ga4Event $event): bool;
}
