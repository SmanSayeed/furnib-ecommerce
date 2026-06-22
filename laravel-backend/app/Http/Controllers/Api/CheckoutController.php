<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Marketing\SendPurchaseEvent;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\SendOrderConfirmation;
use App\DTOs\PlaceOrderData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Support\Capi\CapiUserData;
use DomainException;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly PlaceOrder $placeOrder,
        private readonly SendOrderConfirmation $sendConfirmation,
        private readonly SendPurchaseEvent $sendPurchaseEvent,
    ) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
        );

        try {
            $order = $this->placeOrder->handle($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->sendConfirmation->handle($order);

        // Fire the Purchase to Meta at placement so COD orders (which never hit
        // the payment gateway) are still tracked. Shares the deterministic
        // event_id with the browser Pixel + any later online-payment fire, so
        // Meta de-duplicates and counts the order exactly once.
        $cookie = static fn (string $name, string $header): ?string => match (true) {
            is_string($v = $request->cookie($name)) => $v,
            is_string($h = $request->header($header)) => $h,
            default => null,
        };

        $this->sendPurchaseEvent->handle(
            $order,
            new CapiUserData(
                email: $validated['customer']['email'] ?? null,
                phone: $validated['customer']['mobile'],
                ip: $request->ip(),
                userAgent: (string) $request->userAgent(),
                fbp: $cookie('_fbp', 'X-Fbp'),
                fbc: $cookie('_fbc', 'X-Fbc'),
            ),
            $request->header('referer') ?? config('app.frontend_url'),
        );

        return (new OrderResource($order->loadMissing('items')))->response()->setStatusCode(201);
    }
}
