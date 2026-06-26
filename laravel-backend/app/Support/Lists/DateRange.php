<?php

declare(strict_types=1);

namespace App\Support\Lists;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves an admin list date filter to an inclusive [from, to] window.
 *
 * Boundaries are computed in the storefront's display timezone (Asia/Dhaka) so
 * "today" / "this month" mean the local day/month, then converted to UTC — the
 * timezone the database stores `created_at` in. The global app timezone stays
 * UTC; we never shift stored timestamps.
 *
 * All bounds are UTC CarbonImmutable instances; an "all" / unknown preset yields
 * a null, unbounded range.
 */
final class DateRange
{
    public const TZ = 'Asia/Dhaka';

    /** @var list<string> */
    public const PRESETS = ['today', 'yesterday', 'last_7', 'this_month', 'last_month', 'custom', 'all'];

    private function __construct(
        public readonly ?CarbonImmutable $from,
        public readonly ?CarbonImmutable $to,
        public readonly string $preset,
    ) {}

    public static function fromPreset(string $preset, ?string $from = null, ?string $to = null, string $tz = self::TZ): self
    {
        $now = CarbonImmutable::now($tz);

        return match ($preset) {
            'today' => self::bounded($now->startOfDay(), $now->endOfDay(), 'today'),
            'yesterday' => self::bounded($now->subDay()->startOfDay(), $now->subDay()->endOfDay(), 'yesterday'),
            'last_7' => self::bounded($now->subDays(6)->startOfDay(), $now->endOfDay(), 'last_7'),
            'this_month' => self::bounded($now->startOfMonth(), $now->endOfMonth(), 'this_month'),
            'last_month' => self::bounded(
                $now->subMonthNoOverflow()->startOfMonth(),
                $now->subMonthNoOverflow()->endOfMonth(),
                'last_month',
            ),
            'custom' => self::custom($from, $to, $tz),
            default => new self(null, null, 'all'),
        };
    }

    private static function bounded(CarbonImmutable $from, CarbonImmutable $to, string $preset): self
    {
        return new self($from->utc(), $to->utc(), $preset);
    }

    private static function custom(?string $from, ?string $to, string $tz): self
    {
        $start = filled($from) ? CarbonImmutable::parse($from, $tz)->startOfDay()->utc() : null;
        $end = filled($to) ? CarbonImmutable::parse($to, $tz)->endOfDay()->utc() : null;

        return new self($start, $end, 'custom');
    }

    public function isAll(): bool
    {
        return $this->from === null && $this->to === null;
    }

    /**
     * Constrain a query to this window on the given timestamp column.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, string $column = 'created_at'): Builder
    {
        if ($this->from !== null) {
            $query->where($column, '>=', $this->from);
        }

        if ($this->to !== null) {
            $query->where($column, '<=', $this->to);
        }

        return $query;
    }
}
