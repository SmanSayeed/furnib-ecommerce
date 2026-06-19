<?php

declare(strict_types=1);

namespace App\Support\Capi;

use App\Models\Order;

/**
 * Meta Conversions API abstraction. The access token is a server-side secret
 * read from encrypted settings and never returned to the client. Faked in tests.
 */
interface ConversionApi
{
    /**
     * Send a server-side Purchase event. The event_id is shared with the
     * browser Pixel so Meta can de-duplicate the two. Returns false when the
     * integration is not configured or the send was not accepted.
     */
    public function purchase(Order $order, string $eventId): bool;
}
