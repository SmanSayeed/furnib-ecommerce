<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\DTOs\PlaceOrderData;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Services\Orders\CustomerService;
use App\Support\AdvancePayment;
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
            $advanceMinor = 0;
            $needsShippingAdvance = false;
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

                // Per-line advance (full / percentage / fixed-amount). The
                // shipping-charge rule is order-level, added once below.
                $advanceMinor += AdvancePayment::forLine(
                    Money::fromMinor($lineMinor),
                    (bool) $product->is_advance_payment,
                    $product->advance_payment_type,
                    $product->partial_amount_type,
                    $product->partial_amount,
                )->toMinor();

                if ($product->is_advance_payment
                    && $product->advance_payment_type === 'partial'
                    && $product->partial_amount_type === 'shipping') {
                    $needsShippingAdvance = true;
                }

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

            // Shipping-charge advance: the customer must prepay the delivery fee
            // of the zone they selected. A zone is therefore required.
            if ($needsShippingAdvance) {
                if ($zone === null) {
                    throw new DomainException('Please select a delivery area for this order.');
                }
                $advanceMinor += $shippingMinor;
            }

            $totalMinor = $subtotalMinor + $shippingMinor;
            // Advance can never exceed the order total.
            $advanceMinor = min($advanceMinor, $totalMinor);

            $order = Order::query()->create([
                'order_no' => OrderNumber::generate(),
                'customer_id' => $customer->id,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'subtotal' => Money::fromMinor($subtotalMinor),
                'shipping_cost' => Money::fromMinor($shippingMinor),
                'total' => Money::fromMinor($totalMinor),
                'advance_amount' => Money::fromMinor($advanceMinor),
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
