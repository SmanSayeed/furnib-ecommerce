<?php

declare(strict_types=1);

namespace App\Support\Capi;

/**
 * Meta Conversions API abstraction. The access token is a server-side secret
 * read from encrypted settings and never returned to the client. Faked in tests.
 */
interface ConversionApi
{
    /**
     * Send one server-side event. The event carries an `event_id` shared with
     * the browser Pixel so Meta de-duplicates the two copies. Returns false when
     * the integration is not configured or the send was not accepted.
     */
    public function send(CapiEvent $event): bool;
}
