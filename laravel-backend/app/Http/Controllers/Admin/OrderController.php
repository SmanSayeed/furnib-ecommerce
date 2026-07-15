<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\ApplyOrderDiscount;
use App\Actions\Orders\UpdateOrderCustomer;
use App\Enums\OrderNotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApplyOrderDiscountRequest;
use App\Http\Requests\Admin\OrderBulkStatusRequest;
use App\Http\Requests\Admin\UpdateOrderCustomerRequest;
use App\Http\Requests\Admin\UpdateOrderNoteRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Requests\Admin\UpdatePendingReasonRequest;
use App\Models\Courier;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ShippingZone;
use App\Repositories\Eloquent\OrderRepository;
use App\Services\Courier\CustomerCourierStats;
use App\Services\Notifications\OrderNotificationService;
use App\Support\Money;
use App\Support\Orders\PayLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
                'pending_note' => $o->status === 'pending' ? $o->pending_note : null,
                'admin_note' => $o->admin_note,
                'payment_status' => $o->payment_status,
                // The booked courier (name snapshot) + its status, or null when the
                // order has no shipment yet — drives the list's Courier column.
                'courier' => $o->shipment?->courier,
                'courier_status' => $o->shipment?->status,
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
                'pending_reason' => $listQuery->filters['pending_reason'] ?? '',
                'sort' => $listQuery->sort,
                'dir' => $listQuery->dir,
                'range' => $listQuery->dateRange->preset,
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
                'per_page' => (string) $listQuery->perPage,
            ],
            'statuses' => Order::STATUSES,
            'paymentStatuses' => Order::PAYMENT_STATUSES,
            'pendingReasons' => Order::PENDING_REASONS,
            // Legal next statuses per current status — drives the inline per-row
            // status dropdown (the server still re-validates every transition).
            'transitions' => Order::TRANSITIONS,
            // Active couriers for the inline "Set courier" control in the list.
            'couriers' => $this->activeCourierOptions(),
            // Booking is guarded by orders.manage; hide the set control otherwise.
            'canBook' => (bool) $request->user()?->can('orders.manage'),
        ]);
    }

    /**
     * Active couriers the admin can book an order with (id/name/driver + whether
     * it is API-driven and configured). Shared by the order list and detail.
     *
     * @return array<int, array<string, mixed>>
     */
    private function activeCourierOptions(): array
    {
        return Courier::query()
            ->active()
            ->orderBy('position_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Courier $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'driver' => $c->driver,
                'is_api' => $c->isApi(),
                'configured' => $c->isConfigured(),
            ])
            ->all();
    }

    public function show(Request $request, Order $order, CustomerCourierStats $courierStats): Response
    {
        $order->load(['items', 'customer', 'shippingZone', 'shipment', 'payments' => fn ($q) => $q->latest('id')]);

        $dueMinor = max(0, $order->total->toMinor() - $order->advance_paid->toMinor());

        // Our own courier fraud/return-ratio signal for this customer's phone —
        // lets the admin spot a repeat "cancel on delivery" buyer before shipping.
        $mobile = (string) ($order->customer->mobile ?? '');
        $fraud = $mobile !== '' ? $courierStats->forPhone($mobile) : null;

        return Inertia::render('orders/show', [
            'order' => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'status' => $order->status,
                'pending_reason' => $order->pending_reason,
                'pending_note' => $order->pending_note,
                'payment_status' => $order->payment_status,
                'subtotal' => $order->subtotal->format(),
                'discount' => $order->discount->format(),
                'discount_note' => $order->discount_note,
                'shipping_cost' => $order->shipping_cost->format(),
                'total' => $order->total->format(),
                'advance_paid' => $order->advance_paid->format(),
                'due' => Money::fromMinor($dueMinor)->format(),
                'address' => $order->address,
                'notes' => $order->notes,
                'admin_note' => $order->admin_note,
                // The customer's self-service payment link — copyable/resendable by
                // the admin. The token is an HMAC of the order_no (unguessable).
                'pay_url' => PayLink::for($order),
                'created_at' => $order->created_at?->toDateTimeString(),
                'customer' => [
                    'name' => $order->customer?->name,
                    'mobile' => $order->customer?->mobile,
                    'email' => $order->customer?->email,
                ],
                'shipping_zone' => $order->shippingZone?->name,
                'shipping_zone_id' => $order->shipping_zone_id,
                'items' => $order->items->map(fn ($i): array => [
                    'title' => $i->title,
                    'sku' => $i->sku,
                    'price' => $i->price->format(),
                    // Only present when the line carried a product discount.
                    'original_price' => $i->wasDiscounted() ? $i->original_price->format() : null,
                    'discount_amount' => $i->wasDiscounted() ? $i->discount_amount->format() : null,
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
                // Courier consignment (null until the order is shipped/booked).
                'shipment' => $order->shipment === null ? null : [
                    'courier' => $order->shipment->courier,
                    'consignment_id' => $order->shipment->consignment_id,
                    'tracking_code' => $order->shipment->tracking_code,
                    'status' => $order->shipment->status,
                    'cod_amount' => $order->shipment->cod_amount->format('৳'),
                ],
            ],
            'nextStatuses' => Order::TRANSITIONS[$order->status] ?? [],
            'pendingReasons' => Order::PENDING_REASONS,
            // Active zones for the delivery-address editor. Changing the zone
            // recomputes shipping + total server-side.
            'shippingZones' => ShippingZone::query()->active()->ordered()
                ->get(['id', 'name'])
                ->map(fn (ShippingZone $z): array => ['id' => $z->id, 'name' => $z->name])
                ->all(),
            'canManagePayments' => $request->user()?->can('orders.manage') ?? false,
            // Our own fraud/return-ratio signal for this customer's phone.
            'courierStats' => $fraud,
            // Active couriers the admin can book this order with.
            'couriers' => $this->activeCourierOptions(),
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

    /**
     * The admin's own note. Unlike `pending_note` (gated to pending, and wiped on
     * any forward transition) this survives the whole life of the order, so it can
     * carry the running story: who called, what was promised, why it is late.
     */
    public function updateNote(UpdateOrderNoteRequest $request, Order $order): RedirectResponse
    {
        $order->update(['admin_note' => $request->validated()['admin_note'] ?? null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Admin note saved.')]);

        return back();
    }

    /**
     * Correct the customer (name/mobile/email) and the order's delivery address and
     * zone. A zone change recomputes shipping and the total server-side, so the pay
     * link and the invoice follow automatically.
     */
    public function updateCustomer(
        UpdateOrderCustomerRequest $request,
        Order $order,
        UpdateOrderCustomer $updateCustomer,
    ): RedirectResponse {
        $order->load(['items', 'customer', 'shipment']);

        /** @var array{name: ?string, email: ?string, mobile: string, address: string, shipping_zone_id: ?int} $data */
        $data = $request->validated();

        $consignmentIsStale = $updateCustomer->handle($order, $data);

        Inertia::flash('toast', $consignmentIsStale
            ? [
                'type' => 'warning',
                'message' => __('Saved — but this order is already booked with a courier, which still has the OLD address. Cancel and re-book the consignment.'),
            ]
            : ['type' => 'success', 'message' => __('Customer and address updated.')]);

        return back();
    }

    /**
     * Apply (or clear) an order-level discount. Reduces the total, hence the due
     * and the amount the pay link + invoice charge — so it is guarded on paid /
     * booked / over-discount / below-paid edges inside the action. A zero amount
     * clears any existing discount.
     */
    public function applyDiscount(
        ApplyOrderDiscountRequest $request,
        Order $order,
        ApplyOrderDiscount $applyDiscount,
    ): RedirectResponse {
        $order->loadMissing('shipment');

        $data = $request->validated();

        $applyDiscount->handle(
            $order,
            (int) $request->integer('discount_minor'),
            $data['note'] ?? null,
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Discount applied.')]);

        return back();
    }

    /**
     * Re-send the customer's pay-link SMS (the "Placed" notification). Rate-limited
     * to 3/hour/order so it can never be turned into an SMS-bill DoS. The channel's
     * idempotency guard would otherwise swallow a repeat, so we clear this order's
     * prior "placed" logs first, then send synchronously for immediate feedback.
     * The message is re-rendered from the live order row, so a resend after a
     * discount carries the updated due + pay link automatically.
     */
    public function resendPayLink(
        Request $request,
        Order $order,
        OrderNotificationService $notifications,
    ): RedirectResponse {
        $key = 'resend-pay-link:'.$order->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            throw ValidationException::withMessages([
                'pay_link' => "Too many resends for this order. Try again in about {$minutes} min.",
            ]);
        }

        RateLimiter::hit($key, 3600);

        // Clear this order's "placed" notification logs so the channel idempotency
        // guard lets the message go out again.
        NotificationLog::query()
            ->where('order_id', $order->id)
            ->where('event', OrderNotificationEvent::Placed->value)
            ->delete();

        $notifications->notify($order, OrderNotificationEvent::Placed);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment link SMS resent.')]);

        return back();
    }
}
