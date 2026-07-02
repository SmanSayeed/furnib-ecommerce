<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Repositories\Eloquent\OrderRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderRepository $orders,
    ) {}

    public function index(Request $request): Response
    {
        $listQuery = $this->orders->queryFrom($request);
        $paginator = $this->orders->adminList($listQuery);

        return Inertia::render('orders/index', [
            'orders' => collect($paginator->items())->map(fn (Order $o): array => [
                'id' => $o->id,
                'order_no' => $o->order_no,
                'customer' => $o->customer?->name,
                'mobile' => $o->customer?->mobile,
                'total' => $o->total->format(),
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'created_at' => $o->created_at?->toDateTimeString(),
            ])->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'search' => $listQuery->search ?? '',
                'status' => $listQuery->filters['status'] ?? '',
                'payment_status' => $listQuery->filters['payment_status'] ?? '',
                'sort' => $listQuery->sort,
                'dir' => $listQuery->dir,
                'range' => $listQuery->dateRange->preset,
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
            ],
            'statuses' => Order::STATUSES,
            'paymentStatuses' => Order::PAYMENT_STATUSES,
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load(['items', 'customer', 'shippingZone']);

        return Inertia::render('orders/show', [
            'order' => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'subtotal' => $order->subtotal->format(),
                'shipping_cost' => $order->shipping_cost->format(),
                'total' => $order->total->format(),
                'address' => $order->address,
                'notes' => $order->notes,
                'created_at' => $order->created_at?->toDateTimeString(),
                'customer' => [
                    'name' => $order->customer?->name,
                    'mobile' => $order->customer?->mobile,
                    'email' => $order->customer?->email,
                ],
                'shipping_zone' => $order->shippingZone?->name,
                'items' => $order->items->map(fn ($i): array => [
                    'title' => $i->title,
                    'sku' => $i->sku,
                    'price' => $i->price->format(),
                    'qty' => $i->qty,
                    'line_total' => $i->line_total->format(),
                ])->all(),
            ],
            'nextStatuses' => Order::TRANSITIONS[$order->status] ?? [],
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $status = (string) $request->validated()['status'];

        if (! $order->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => "Cannot change status from {$order->status} to {$status}.",
            ]);
        }

        $order->update(['status' => $status]);

        // No marketing fires here. The Purchase conversion (server-side CAPI +
        // GA4 + TikTok, and the browser dataLayer push) happens once at order
        // placement (see Api\CheckoutController). Admin status changes are purely
        // operational and carry no GTM/tracking.
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order status updated.')]);

        return back();
    }
}
