<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderBulkStatusRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Requests\Admin\UpdatePendingReasonRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Repositories\Eloquent\OrderRepository;
use App\Support\Money;
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
                'pending_reason' => $o->status === 'pending' ? $o->pending_reason : null,
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
                'per_page' => (string) $listQuery->perPage,
            ],
            'statuses' => Order::STATUSES,
            'paymentStatuses' => Order::PAYMENT_STATUSES,
            // Legal next statuses per current status — drives the inline per-row
            // status dropdown (the server still re-validates every transition).
            'transitions' => Order::TRANSITIONS,
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $order->load(['items', 'customer', 'shippingZone', 'payments' => fn ($q) => $q->latest('id')]);

        $dueMinor = max(0, $order->total->toMinor() - $order->advance_paid->toMinor());

        return Inertia::render('orders/show', [
            'order' => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'status' => $order->status,
                'pending_reason' => $order->pending_reason,
                'pending_note' => $order->pending_note,
                'payment_status' => $order->payment_status,
                'subtotal' => $order->subtotal->format(),
                'shipping_cost' => $order->shipping_cost->format(),
                'total' => $order->total->format(),
                'advance_paid' => $order->advance_paid->format(),
                'due' => Money::fromMinor($dueMinor)->format(),
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
                // Payment ledger — gateway + manual entries. Never exposes the
                // encrypted gateway payload, only non-sensitive summary fields.
                'payments' => $order->payments->map(fn (Payment $p): array => [
                    'id' => $p->id,
                    'gateway' => $p->gateway,
                    'amount' => $p->amount->format('৳'),
                    'type' => $p->type,
                    'direction' => $p->direction,
                    'status' => $p->status,
                    'note' => $p->note,
                    'at' => $p->created_at?->toDateTimeString(),
                ])->all(),
            ],
            'nextStatuses' => Order::TRANSITIONS[$order->status] ?? [],
            'pendingReasons' => Order::PENDING_REASONS,
            'canManagePayments' => $request->user()?->can('orders.manage') ?? false,
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

        // Reverting to (or staying) pending keeps the existing reason; moving
        // forward clears the operational note so a stale reason doesn't linger.
        $attributes = ['status' => $status];

        if ($status !== 'pending') {
            $attributes['pending_note'] = null;
        }

        $order->update($attributes);

        // No marketing fires here. The Purchase conversion (server-side CAPI +
        // GA4 + TikTok, and the browser dataLayer push) happens once at order
        // placement (see Api\CheckoutController). Admin status changes are purely
        // operational and carry no GTM/tracking.
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order status updated.')]);

        return back();
    }

    /**
     * Move many orders to a new status at once. Each order is transitioned only
     * if the change is legal for its current status (illegal ones are skipped,
     * not forced), so a mixed selection stays consistent. Targets are explicit
     * ids or every order matching the current filters; the batch is one audit
     * entry.
     */
    public function bulkStatus(OrderBulkStatusRequest $request): RedirectResponse
    {
        $status = (string) $request->validated()['status'];

        // For "all matching", the list filters arrive under a separate `filters`
        // key so they can't collide with the target `status`. Resolve them
        // through a synthetic request so the same whitelist/queryFrom applies.
        $ids = $request->boolean('all_matching')
            ? $this->orders->idsMatching($this->orders->queryFrom(
                Request::create('/', 'GET', (array) $request->input('filters', []))
            ))
            : array_values(array_unique(array_map('intval', $request->validated()['ids'] ?? [])));

        $orders = Order::query()->whereIn('id', $ids)->get();
        $changed = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            if (! $order->canTransitionTo($status)) {
                $skipped++;

                continue;
            }

            $attributes = ['status' => $status];

            if ($status !== 'pending') {
                $attributes['pending_note'] = null;
            }

            $order->update($attributes);
            $changed++;
        }

        $message = $skipped > 0
            ? __(':changed updated, :skipped skipped (illegal transition).', ['changed' => $changed, 'skipped' => $skipped])
            : __(':changed orders updated.', ['changed' => $changed]);

        Inertia::flash('toast', ['type' => $changed > 0 ? 'success' : 'warning', 'message' => $message]);

        return back();
    }

    /**
     * Set the reason a pending order is still open (and an optional note for the
     * "other" reason). Only meaningful while the order is pending; once it moves
     * forward the reason is ignored.
     */
    public function updatePending(UpdatePendingReasonRequest $request, Order $order): RedirectResponse
    {
        if ($order->status !== 'pending') {
            throw ValidationException::withMessages([
                'pending_reason' => 'Only a pending order can have its reason set.',
            ]);
        }

        $data = $request->validated();

        $order->update([
            'pending_reason' => $data['pending_reason'],
            'pending_note' => $data['pending_reason'] === 'other' ? ($data['pending_note'] ?? null) : null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pending reason updated.')]);

        return back();
    }
}
