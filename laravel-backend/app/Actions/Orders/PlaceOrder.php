<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\DTOs\PlaceOrderData;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Services\Orders\CustomerService;
use App\Support\Money;
use App\Support\OrderNumber;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Places a web order: resolves the customer by mobile, snapshots each line's
 * product price/title/sku, computes subtotal + shipping = total server-side,
 * persists the order + items, decrements stock, and captures IP/UA. Runs in a
 * DB transaction so any failure rolls back cleanly. Audit-logged via the
 * model's Auditable trait.
 */
final class PlaceOrder
{
    public function __construct(private readonly CustomerService $customers) {}

    public function handle(PlaceOrderData $data): Order
    {
        if ($data->items === []) {
            throw new DomainException('Order has no items.');
        }

        return DB::transaction(function () use ($data): Order {
            $customer = $this->customers->findOrCreateByMobile(
                $data->customerMobile,
                $data->customerName,
                $data->customerEmail,
            );

            $products = Product::query()
                ->whereIn('id', array_map(static fn (array $i): int => (int) $i['product_id'], $data->items))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotalMinor = 0;
            $lines = [];

            foreach ($data->items as $item) {
                $productId = (int) $item['product_id'];
                $qty = (int) $item['qty'];

                if ($qty < 1) {
                    throw new DomainException('Quantity must be at least 1.');
                }

                $product = $products->get($productId);

                if ($product === null) {
                    throw new DomainException("Unknown product: {$productId}");
                }

                if (! $product->stock_status || $product->stock_amount < $qty) {
                    throw new DomainException("Insufficient stock for: {$product->title}");
                }

                $priceMinor = $product->price->toMinor();
                $lineMinor = $priceMinor * $qty;
                $subtotalMinor += $lineMinor;

                $lines[] = [
                    'product_id' => $product->id,
                    'title' => $product->title,
                    'sku' => $product->sku,
                    'price' => Money::fromMinor($priceMinor),
                    'qty' => $qty,
                    'line_total' => Money::fromMinor($lineMinor),
                ];
            }

            $shippingMinor = 0;
            $zone = $data->shippingZoneId !== null
                ? ShippingZone::query()->find($data->shippingZoneId)
                : null;

            if ($zone !== null) {
                $shippingMinor = $zone->cost->toMinor();
            }

            $order = Order::query()->create([
                'order_no' => OrderNumber::generate(),
                'customer_id' => $customer->id,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'subtotal' => Money::fromMinor($subtotalMinor),
                'shipping_cost' => Money::fromMinor($shippingMinor),
                'total' => Money::fromMinor($subtotalMinor + $shippingMinor),
                'advance_paid' => Money::fromMinor(0),
                'shipping_zone_id' => $zone?->id,
                'address' => $data->address,
                'customer_ip' => $data->ip,
                'user_agent' => $data->userAgent,
                'notes' => $data->notes,
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line);
                $products->get($line['product_id'])?->decrement('stock_amount', $line['qty']);
            }

            return $order->load('items');
        });
    }
}
