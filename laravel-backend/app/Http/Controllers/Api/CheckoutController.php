<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Marketing\ConfirmOrderPurchase;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\SendOrderConfirmation;
use App\DTOs\PlaceOrderData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use DomainException;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly PlaceOrder $placeOrder,
        private readonly SendOrderConfirmation $sendConfirmation,
    ) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // First-party Meta cookies (or their header fallbacks) — persisted on the
        // order so a later admin-confirm Purchase attributes to this customer.
        $cookie = static fn (string $name, string $header): ?string => match (true) {
            is_string($v = $request->cookie($name)) => $v,
            is_string($h = $request->header($header)) => $h,
            default => null,
        };

        $data = new PlaceOrderData(
            items: array_map(
                static fn (array $i): array => [
                    'product_id' => (int) $i['product_id'],
                    'qty' => (int) $i['qty'],
                ],
                $validated['items'],
            ),
            customerMobile: $validated['customer']['mobile'],
            customerName: $validated['customer']['name'] ?? null,
            customerEmail: $validated['customer']['email'] ?? null,
            shippingZoneId: isset($validated['shipping_zone_id']) ? (int) $validated['shipping_zone_id'] : null,
            address: $validated['address'],
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
            notes: $validated['notes'] ?? null,
            fbp: $cookie('_fbp', 'X-Fbp'),
            fbc: $cookie('_fbc', 'X-Fbc'),
            ttp: $cookie('_ttp', 'X-Ttp'),
            ttclid: $cookie('ttclid', 'X-Ttclid'),
            gaClientId: $cookie('_ga_client_id', 'X-Ga-Client-Id'),
        );

        try {
            $order = $this->placeOrder->handle($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->sendConfirmation->handle($order);

        $order->loadMissing(['items.product.category', 'customer', 'shippingZone']);

        // The Purchase conversion fires here — the moment the order is placed —
        // once, server-side (Meta CAPI + TikTok + GA4). The fbp/fbc captured
        // above are persisted on the order so the fire attributes to this
        // customer. It runs AFTER the response is flushed so three outbound
        // marketing HTTP calls never slow the shopper's checkout; the
        // idempotency stamp still guards against any later re-fire. The browser
        // `purchase` dataLayer push (OrderResource `tracking`) shares the same
        // `event_id`, so Meta de-duplicates the two into one counted sale. The
        // closure captures only the order (a serializable model) and resolves
        // the action fresh — never the controller, which isn't serializable.
        dispatch(function () use ($order): void {
            app(ConfirmOrderPurchase::class)->handle($order);
        })->afterResponse();

        return (new OrderResource($order))->response()->setStatusCode(201);
    }
}
