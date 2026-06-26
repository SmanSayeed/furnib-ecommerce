<?php

declare(strict_types=1);

namespace App\Support\Lists;

use Illuminate\Http\Request;

/**
 * A parsed, sanitised admin list request: keyword search, equality filters,
 * whitelisted sort + direction, a date window, and pagination size.
 *
 * Everything is validated against a per-resource config so user input can never
 * inject an arbitrary column into ORDER BY / WHERE. Unknown sort → default;
 * unlisted filters → dropped; direction normalised to asc|desc.
 *
 * Config shape:
 *   [
 *     'searchColumns' => ['order_no', 'customer.name'], // LIKE OR-group (relation.col allowed)
 *     'filters'       => ['status', 'payment_status'],  // exact-match whitelist
 *     'sorts'         => ['created_at', 'total'],        // ORDER BY whitelist
 *     'defaultSort'   => 'created_at',
 *     'defaultDir'    => 'desc',                          // optional, defaults to desc
 *     'perPage'       => 15,                              // optional
 *   ]
 */
final class ListQuery
{
    /**
     * @param  list<string>  $searchColumns
     * @param  array<string, string>  $filters
     */
    public function __construct(
        public readonly ?string $search,
        public readonly array $searchColumns,
        public readonly array $filters,
        public readonly string $sort,
        public readonly string $dir,
        public readonly DateRange $dateRange,
        public readonly int $perPage,
    ) {}

    /**
     * @param  array{
     *     searchColumns?: list<string>,
     *     filters?: list<string>,
     *     sorts?: list<string>,
     *     defaultSort?: string,
     *     defaultDir?: string,
     *     perPage?: int
     * }  $config
     */
    public static function fromRequest(Request $request, array $config): self
    {
        $sorts = $config['sorts'] ?? [];
        $defaultSort = $config['defaultSort'] ?? 'created_at';
        $defaultDir = strtolower($config['defaultDir'] ?? 'desc');
        $defaultDir = in_array($defaultDir, ['asc', 'desc'], true) ? $defaultDir : 'desc';

        $sort = (string) $request->query('sort', $defaultSort);
        if (! in_array($sort, $sorts, true)) {
            $sort = $defaultSort;
        }

        $dir = strtolower((string) $request->query('dir', $defaultDir));
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = $defaultDir;
        }

        $search = trim((string) $request->query('search', ''));
        $search = $search === '' ? null : $search;

        $filters = [];
        foreach ($config['filters'] ?? [] as $column) {
            $value = $request->query($column);
            if (is_string($value) && trim($value) !== '') {
                $filters[$column] = trim($value);
            }
        }

        $preset = (string) $request->query('range', 'all');
        if (! in_array($preset, DateRange::PRESETS, true)) {
            $preset = 'all';
        }
        $from = $request->query('from');
        $to = $request->query('to');
        $dateRange = DateRange::fromPreset(
            $preset,
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        );

        $perPage = (int) $request->query('per_page', (string) ($config['perPage'] ?? 15));
        $perPage = max(1, min(100, $perPage));

        return new self(
            search: $search,
            searchColumns: $config['searchColumns'] ?? [],
            filters: $filters,
            sort: $sort,
            dir: $dir,
            dateRange: $dateRange,
            perPage: $perPage,
        );
    }
}
