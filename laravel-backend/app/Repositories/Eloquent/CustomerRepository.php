<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Support\Lists\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Read model for the admin customer list. Aggregates (order count, total spent)
 * are computed with withCount/withSum — one query, no N+1. Total spent counts
 * only orders whose payment landed (paid or partial), in integer paisa.
 *
 * Sorting is whitelisted and maps UI sort keys to real columns / aggregate
 * aliases, so it stays injection-safe while allowing aggregate-alias sorting
 * (which the generic AppliesListFilters scope can't qualify).
 */
final class CustomerRepository
{
    private const SORT_MAP = [
        'name' => 'name',
        'joined' => 'created_at',
        'orders_count' => 'orders_count',
        'total_spent' => 'total_spent_minor',
    ];

    /**
     * @return array{
     *     searchColumns: list<string>,
     *     sorts: list<string>,
     *     defaultSort: string,
     *     defaultDir: string,
     *     perPage: int
     * }
     */
    public static function listConfig(): array
    {
        return [
            'searchColumns' => ['name', 'mobile', 'email'],
            'sorts' => ['name', 'joined', 'orders_count', 'total_spent'],
            'defaultSort' => 'joined',
            'defaultDir' => 'desc',
            'perPage' => 20,
        ];
    }

    public function queryFrom(Request $request): ListQuery
    {
        return ListQuery::fromRequest($request, self::listConfig());
    }

    /** @return LengthAwarePaginator<int, Customer> */
    public function adminList(ListQuery $list): LengthAwarePaginator
    {
        $query = Customer::query()
            ->withCount('orders')
            ->withSum(
                ['orders as total_spent_minor' => fn (Builder $q) => $q->whereIn('payment_status', ['paid', 'partial'])],
                'total',
            );

        if ($list->search !== null) {
            $term = '%'.$list->search.'%';
            $query->where(function (Builder $group) use ($list, $term): void {
                foreach ($list->searchColumns as $column) {
                    $group->orWhere($column, 'like', $term);
                }
            });
        }

        $list->dateRange->apply($query, 'customers.created_at');

        $column = self::SORT_MAP[$list->sort] ?? 'created_at';
        $direction = $list->dir === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($column, $direction)
            ->paginate($list->perPage)
            ->withQueryString();
    }
}
