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
            'searchColumns' => ['order_no', 'customer.name', 'customer.mobile', 'admin_note'],
            // pending_reason was displayed in the list but never filterable — the
            // whitelist silently dropped it, so ?pending_reason=… did nothing.
            'filters' => ['status', 'payment_status', 'pending_reason'],
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
            ->with(['customer', 'shipment:id,order_id,courier,status'])
            ->applyList($query)
            ->paginate($query->perPage)
            ->withQueryString();
    }

    /**
     * Primary keys of every order matching the list filters (no pagination),
     * capped so a "select all matching" bulk/print action stays bounded. Uses
     * the same injection-safe whitelist as the list.
     *
     * @return list<int>
     */
    public function idsMatching(ListQuery $query, int $cap = 500): array
    {
        return array_values(
            Order::query()
                ->applyList($query)
                ->limit($cap)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all()
        );
    }
}
