<?php

declare(strict_types=1);

namespace App\Support\Courier;

use RuntimeException;

/**
 * A courier provider refused us, or we could not reach it.
 *
 * Before this existed, every SteadFast failure — a 401 from a wrong key, a 422
 * from a duplicate invoice, an HTML error page, a hung socket — collapsed into
 * the same useless "Failed to create SteadFast consignment.", which then escaped
 * an un-caught controller and became a white 500 page. The admin had no way to
 * tell a bad credential from a blocked firewall.
 *
 * The message is written to be shown to the admin as-is. Provider error bodies
 * are safe to surface: our credentials travel in request HEADERS, never in the
 * response. The body is still truncated, and request headers are never logged.
 */
final class CourierException extends RuntimeException
{
    private const MAX_BODY = 300;

    public static function http(string $courier, int $status, string $body): self
    {
        $detail = self::summarize($body);

        $hint = $status === 401 || $status === 403
            ? " Check the credentials, and confirm this server's IP is whitelisted in the {$courier} panel."
            : '';

        return new self(
            "{$courier} rejected the request (HTTP {$status})."
            .($detail !== '' ? " {$detail}" : '')
            .$hint,
        );
    }

    public static function missingCredentials(string $courier): self
    {
        return new self("{$courier} has no API credentials yet. Add them under Shipping → Couriers.");
    }

    public static function unreachable(string $courier, string $reason): self
    {
        return new self("Could not reach {$courier}: {$reason}");
    }

    /** Collapse a provider body to a single short, printable line. */
    private static function summarize(string $body): string
    {
        $body = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');

        if ($body === '') {
            return '';
        }

        return strlen($body) > self::MAX_BODY
            ? substr($body, 0, self::MAX_BODY).'…'
            : $body;
    }
}
