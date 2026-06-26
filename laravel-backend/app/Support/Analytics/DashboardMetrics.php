<?php

declare(strict_types=1);

namespace App\Support\Analytics;

use App\Models\Customer;
use App\Models\Order;
use App\Support\Lists\DateRange;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Windowed order/revenue analytics for the admin dashboard. Revenue counts only
 * orders whose payment is `paid`. The daily series buckets are grouped in the
 * display timezone (Asia/Dhaka) in PHP — not SQL — so it stays correct and
 * database-agnostic (sqlite tests included). All money is integer paisa.
 */
final class DashboardMetrics
{
    /**
     * @return array{
     *     orders: int,
     *     revenue_minor: int,
     *     advance_minor: int,
     *     new_customers: int,
     *     paid_count: int,
     *     aov_minor: int
     * }
     */
    public function summary(DateRange $range): array
    {
        $ordersCount = $this->scopedOrders($range)->count();
        $revenueMinor = (int) $this->scopedOrders($range)->where('payment_status', 'paid')->sum('total');
        $paidCount = $this->scopedOrders($range)->where('payment_status', 'paid')->count();
        $advanceMinor = (int) $this->scopedOrders($range)->sum('advance_paid');

        $newCustomers = Customer::query()
            ->tap(fn (Builder $q) => $range->apply($q, 'created_at'))
            ->count();

        $aovMinor = $paidCount > 0 ? intdiv($revenueMinor, $paidCount) : 0;

        return [
            'orders' => $ordersCount,
            'revenue_minor' => $revenueMinor,
            'advance_minor' => $advanceMinor,
            'new_customers' => $newCustomers,
            'paid_count' => $paidCount,
            'aov_minor' => $aovMinor,
        ];
    }

    /**
     * Per-day orders count + paid revenue across the window (display timezone).
     *
     * @return list<array{date: string, orders: int, revenue: float}>
     */
    public function dailySeries(DateRange $range): array
    {
        if ($range->from === null || $range->to === null) {
            return [];
        }

        $tz = DateRange::TZ;
        $cursor = $range->from->setTimezone($tz)->startOfDay();
        $last = $range->to->setTimezone($tz)->startOfDay();

        /** @var array<string, array{date: string, orders: int, revenue_minor: int}> $buckets */
        $buckets = [];
        while ($cursor <= $last) {
            $buckets[$cursor->toDateString()] = [
                'date' => $cursor->toDateString(),
                'orders' => 0,
                'revenue_minor' => 0,
            ];
            $cursor = $cursor->addDay();
        }

        $orders = $this->scopedOrders($range)->get(['created_at', 'total', 'payment_status']);

        foreach ($orders as $order) {
            $key = $order->created_at?->setTimezone($tz)->toDateString();

            if ($key === null || ! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['orders']++;

            if ($order->payment_status === 'paid') {
                $buckets[$key]['revenue_minor'] += $order->total->toMinor();
            }
        }

        return array_values(array_map(
            fn (array $b): array => [
                'date' => $b['date'],
                'orders' => $b['orders'],
                'revenue' => Money::fromMinor($b['revenue_minor'])->toDisplay(),
            ],
            $buckets,
        ));
    }

    /** @return Builder<Order> */
    private function scopedOrders(DateRange $range): Builder
    {
        $query = Order::query();
        $range->apply($query, 'created_at');

        return $query;
    }
}
