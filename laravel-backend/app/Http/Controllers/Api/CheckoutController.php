<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

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

        return (new OrderResource($order->loadMissing('items')))->response()->setStatusCode(201);
    }
}
