<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'from', 'to']);

        $paginator = Order::query()
            ->with('customer')
            ->when(filled($filters['status'] ?? null), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(filled($filters['search'] ?? null), function (Builder $q) use ($filters): void {
                $search = (string) $filters['search'];
                $q->where(function (Builder $inner) use ($search): void {
                    $inner->where('order_no', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $c) => $c
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%"));
                });
            })
            ->when(filled($filters['from'] ?? null), fn (Builder $q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn (Builder $q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->latest()
            ->paginate(20);

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
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'statuses' => Order::STATUSES,
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

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order status updated.')]);

        return back();
    }
}
