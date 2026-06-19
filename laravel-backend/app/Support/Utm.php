<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Extracts UTM campaign parameters from a URL (or its query string).
 */
final class Utm
{
    /**
     * @return array{utm_source: ?string, utm_medium: ?string, utm_campaign: ?string}
     */
    public static function parse(?string $url): array
    {
        $query = $url === null ? '' : (string) (parse_url($url, PHP_URL_QUERY) ?? '');

        // Allow a bare query string too (e.g. "utm_source=fb&utm_medium=cpc").
        if ($query === '' && $url !== null && str_contains($url, '=') && ! str_contains($url, '://')) {
            $query = ltrim($url, '?');
        }

        parse_str($query, $params);

        return [
            'utm_source' => self::value($params['utm_source'] ?? null),
            'utm_medium' => self::value($params['utm_medium'] ?? null),
            'utm_campaign' => self::value($params['utm_campaign'] ?? null),
        ];
    }

    private static function value(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
