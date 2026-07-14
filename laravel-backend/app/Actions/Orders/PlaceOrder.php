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
 * effective (discount-aware) price/title/sku plus the saving, computes
 * subtotal + shipping = total server-side,
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
                ->with('shippingCharges')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotalMinor = 0;
            $advanceMinor = 0;
            $needsShippingAdvance = false;
            $fullAdvanceInOrder = false;
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

                // The customer is charged what the storefront advertised: the
                // discounted price when there is a real discount, otherwise the
                // regular price. Resolved from the DB inside the lock — the client
                // never sends money.
                $priceMinor = $product->effectivePrice()->toMinor();
                $regularMinor = $product->price->toMinor();
                $discountedLine = $product->effectiveDiscount() !== null;

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

                // A shipping-charge advance only makes sense when the product
                // actually incurs delivery: a free-shipping product has nothing
                // to prepay, so it neither triggers the advance nor forces a zone.
                if ($product->is_advance_payment
                    && $product->advance_payment_type === 'partial'
                    && $product->partial_amount_type === 'shipping'
                    && $product->shipping_charge_allowed) {
                    $needsShippingAdvance = true;
                }

                // A FULL advance prepays the entire order, so it must include the
                // delivery charge too (not just the product price).
                if ($product->is_advance_payment && $product->advance_payment_type === 'full') {
                    $fullAdvanceInOrder = true;
                }

                $lines[] = [
                    'product_id' => $product->id,
                    'title' => $product->title,
                    'sku' => $product->sku,
                    'price' => Money::fromMinor($priceMinor),
                    // Snapshot the saving so it survives the order: NULL when the
                    // line was not discounted, so historic rows stay valid.
                    'original_price' => $discountedLine ? Money::fromMinor($regularMinor) : null,
                    'discount_amount' => Money::fromMinor(
                        $discountedLine ? ($regularMinor - $priceMinor) * $qty : 0,
                    ),
                    'qty' => $qty,
                    'line_total' => Money::fromMinor($lineMinor),
                ];
            }

            // Resolve the selected zone. An explicitly chosen zone must exist and
            // be active — a missing/inactive zone is rejected rather than silently
            // shipping for free.
            $zone = null;
            if ($data->shippingZoneId !== null) {
                $zone = ShippingZone::query()->active()->find($data->shippingZoneId);

                if ($zone === null) {
                    throw new DomainException('Selected shipping zone is unavailable.');
                }
            }

            // Effective shipping = zone base + Σ per-line (product per-unit extra
            // for this zone × qty). A product flagged shipping_charge_allowed =
            // false never contributes: not its per-unit extra, and not a share of
            // the zone base. The base is charged once, but only when at least one
            // line in the cart is chargeable — an all-free cart ships free (0).
            $shippingMinor = 0;
            if ($zone !== null) {
                $anyChargeable = false;

                foreach ($lines as $line) {
                    $product = $products->get($line['product_id']);

                    if ($product === null || ! $product->shipping_charge_allowed) {
                        continue;
                    }

                    $anyChargeable = true;
                    $shippingMinor += $product->extraPerUnitMinorFor($zone->id) * $line['qty'];
                }

                if ($anyChargeable) {
                    $shippingMinor += $zone->cost->toMinor();
                }
            }

            // Shipping-charge advance (partial → shipping): the customer prepays
            // the delivery fee of the zone they selected, so a zone is required.
            // A FULL advance also prepays delivery — the whole order is paid up
            // front — but it does not force a zone (0 shipping if none is chosen).
            if ($needsShippingAdvance) {
                if ($zone === null) {
                    throw new DomainException('Please select a delivery area for this order.');
                }
                $advanceMinor += $shippingMinor;
            } elseif ($fullAdvanceInOrder) {
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
                'fbp' => $data->fbp,
                'fbc' => $data->fbc,
                'ttp' => $data->ttp,
                'ttclid' => $data->ttclid,
                'ga_client_id' => $data->gaClientId,
                'notes' => $data->notes,
                // Compliance #11 — proof of policy acceptance (enforced by the
                // FormRequest). IP matches the same value captured for the order.
                'terms_accepted_at' => now(),
                'terms_ip' => $data->ip,
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line);
                $products->get($line['product_id'])?->decrement('stock_amount', $line['qty']);
            }

            return $order->load('items');
        });
    }
}
