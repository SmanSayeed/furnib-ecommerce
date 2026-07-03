<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\GenerateInvoicePdf;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Repositories\Eloquent\OrderRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    /** Hard cap on how many documents one batch may render. */
    private const MAX_BATCH = 300;

    public function __construct(
        private readonly GenerateInvoicePdf $generator,
        private readonly OrderRepository $orders,
    ) {}

    public function show(Order $order): Response
    {
        $pdf = $this->generator->handle($order);

        return $pdf->download("invoice-{$order->order_no}.pdf");
    }

    /**
     * Chained A4 invoices (one order per page) for a selection of orders.
     */
    public function bulkInvoices(Request $request): Response
    {
        $orders = $this->resolveOrders($request);
        abort_if($orders->isEmpty(), 404);

        return $this->generator->bulkInvoices($orders)
            ->download('invoices-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * Compact courier payslips (three per A4) for a selection of orders.
     */
    public function payslips(Request $request): Response
    {
        $orders = $this->resolveOrders($request);
        abort_if($orders->isEmpty(), 404);

        return $this->generator->payslips($orders)
            ->download('payslips-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * Resolve the target orders from either an explicit id list (`ids=1,2,3`) or
     * "all rows matching the current filters" (`all_matching=1` + the same filter
     * query the list uses), capped to a safe batch size. All id handling is
     * whitelist/param-bound — no raw input reaches SQL.
     *
     * @return Collection<int, Order>
     */
    private function resolveOrders(Request $request): Collection
    {
        if ($request->boolean('all_matching')) {
            $ids = $this->orders->idsMatching($this->orders->queryFrom($request), self::MAX_BATCH);
        } else {
            $ids = collect(explode(',', (string) $request->query('ids', '')))
                ->map(static fn ($id): int => (int) trim($id))
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->take(self::MAX_BATCH)
                ->values()
                ->all();
        }

        if ($ids === []) {
            return Order::query()->whereRaw('1 = 0')->get();
        }

        return Order::query()
            ->with(['items', 'customer', 'shippingZone'])
            ->whereIn('id', $ids)
            ->latest('created_at')
            ->get();
    }
}
