<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Support\Lists\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Read model for the admin order list. The whitelist lives here so both the
 * order list and the invoice list (a payment-status projection of orders) share
 * one injection-safe filter/sort contract.
 */
final class OrderRepository
{
    /**
     * @return array{
     *     searchColumns: list<string>,
     *     filters: list<string>,
     *     sorts: list<string>,
     *     defaultSort: string,
     *     defaultDir: string,
     *     perPage: int
     * }
     */
    public static function listConfig(): array
    {
        return [
            'searchColumns' => ['order_no', 'customer.name', 'customer.mobile'],
            'filters' => ['status', 'payment_status'],
            'sorts' => ['created_at', 'total', 'status'],
            'defaultSort' => 'created_at',
            'defaultDir' => 'desc',
            'perPage' => 20,
        ];
    }

    public function queryFrom(Request $request): ListQuery
    {
        return ListQuery::fromRequest($request, self::listConfig());
    }

    /** @return LengthAwarePaginator<int, Order> */
    public function adminList(ListQuery $query): LengthAwarePaginator
    {
        return Order::query()
            ->with('customer')
            ->applyList($query)
            ->paginate($query->perPage)
            ->withQueryString();
    }
}
