<?php

declare(strict_types=1);

namespace App\Support\Sms;

/**
 * Optional capability (ISP): a gateway that can report the provider's id for the
 * message it just sent, so we can later match a delivery report (DLR) back to it.
 * Drivers without provider ids (e.g. the log driver) simply don't implement this.
 */
interface ProvidesMessageId
{
    /** Provider id of the most recent successful send, or null. */
    public function lastMessageId(): ?string;
}
