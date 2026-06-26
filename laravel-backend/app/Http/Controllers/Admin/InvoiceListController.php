<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Repositories\Eloquent\OrderRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin invoice list (gated `orders.view`). An invoice is a projection of an
 * order — there is no separate invoice entity — so this reuses the order read
 * model and links each row to the existing per-order invoice PDF route.
 */
class InvoiceListController extends Controller
{
    public function __construct(private readonly OrderRepository $orders) {}

    public function index(Request $request): Response
    {
        $listQuery = $this->orders->queryFrom($request);
        $paginator = $this->orders->adminList($listQuery);

        return Inertia::render('invoices/index', [
            'invoices' => collect($paginator->items())->map(fn (Order $o): array => [
                'id' => $o->id,
                'invoice_no' => $o->order_no,
                'customer' => $o->customer?->name,
                'mobile' => $o->customer?->mobile,
                'total' => $o->total->format(),
                'payment_status' => $o->payment_status,
                'created_at' => $o->created_at?->toDateString(),
            ])->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'search' => $listQuery->search ?? '',
                'payment_status' => $listQuery->filters['payment_status'] ?? '',
                'sort' => $listQuery->sort,
                'dir' => $listQuery->dir,
                'range' => $listQuery->dateRange->preset,
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
            ],
            'paymentStatuses' => Order::PAYMENT_STATUSES,
        ]);
    }
}
