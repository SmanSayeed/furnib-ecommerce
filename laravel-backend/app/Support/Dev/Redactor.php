<?php

declare(strict_types=1);

namespace App\Support\Dev;

/**
 * Masks secret-looking values in command output / log text before it is shown
 * in the admin. Best-effort defence so an `about`/migrate dump or a stack trace
 * never surfaces an APP_KEY, DB password, or token.
 */
final class Redactor
{
    private const MASK = '***REDACTED***';

    public static function scrub(string $text): string
    {
        $patterns = [
            // Laravel APP_KEY
            '/base64:[A-Za-z0-9+\/=]{20,}/',
            // Bearer tokens (also when standalone, not after a key)
            '/(Bearer\s+)[A-Za-z0-9._\-]+/i',
            // key=value / key: value where the key name looks sensitive — mask
            // the whole value to end of line (handles multi-token values).
            '/((?:password|passwd|secret|token|api[_-]?key|access[_-]?key|app[_-]?key|private[_-]?key|authorization)\s*[=:]\s*).+/i',
        ];

        $replacements = [
            self::MASK,
            '$1'.self::MASK,
            '$1'.self::MASK,
        ];

        return (string) preg_replace($patterns, $replacements, $text);
    }
}
