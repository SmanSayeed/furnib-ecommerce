# Tasks — multi-qty-shipping-rate

## The propagation guarantee (why this is safe)

Shipping is computed **once**, in `PlaceOrder`, and persisted to `orders.shipping_cost` + `orders.total`. Everything downstream reads that row:

| Surface | Reads |
|---|---|
| SSLCommerz pay link / gateway amount | `PaymentAmount::for()` → `orders.total` |
| Invoice PDF | `orders.shipping_cost`, `orders.total` |
| Admin order detail + list | the same row |
| Order SMS (`{total}`, `{due}`) | the same row |
| Courier COD amount | `orders.total − advance_paid` |
| Admin zone-change recompute | `ShippingCalculator` (the same service) |

So there are exactly **two** places the formula is written: `ShippingCalculator` (server, authoritative) and `CheckoutForm.tsx` (storefront preview). Both are changed here, and a test pins them to the same number.

## Phase 1 — RED
- [ ] 1.1 `tests/Unit/ProductShippingExtraTest.php` — the qty-aware extra: enabled+multi · qty 1 · option off · no multi rate · multi = 0 · free-shipping product. **Fails: the method takes no qty.**
- [ ] 1.2 `tests/Feature/Orders/MultiQtyShippingTest.php` — 3 chairs Inside Dhaka = ৳120 (80 + 20 + 10×2). **Fails: charges ৳140.**

## Phase 2 — GREEN: schema + rule
- [ ] 2.1 Migration: `products.multi_qty_shipping_enabled` (boolean, default false) + `product_shipping_charges.multi_extra_cost` (unsignedBigInteger, nullable, paisa)
- [ ] 2.2 `Product` fillable + cast; `ProductShippingCharge` fillable + `MoneyCast` on `multi_extra_cost`
- [ ] 2.3 `Product::extraMinorFor(int $zoneId, int $qty): int` — replaces `extraPerUnitMinorFor()`, which cannot express the rule. Keeps returning 0 for a free-shipping product.
- [ ] 2.4 `ShippingCalculator` passes the line qty
- [ ] 2.5 Phase-1 tests green
- [ ] 2.6 **Regression guard**: `PlaceOrderTest`, `FreeShippingProductTest`, `ProductShippingZonesTest` stay green **untouched** — the option defaults to off, so nothing existing may move

## Phase 3 — GREEN: the money reaches the gateway
- [ ] 3.1 Feature test: place a 3-chair order via `POST /api/v1/orders`, assert `shipping_cost` ৳120 and `total` = subtotal + ৳120
- [ ] 3.2 Feature test: `PaymentAmount::for($order, 'full')` on that order returns the same total (the pay link and SSLCommerz follow the row — this pins it)
- [ ] 3.3 Feature test: the invoice PDF for that order renders the ৳120 delivery line
- [ ] 3.4 Feature test: an admin zone change on a multi-qty order recomputes with the same rule (`UpdateOrderCustomer` → `ShippingCalculator`)

## Phase 4 — admin form
- [ ] 4.1 `Admin\ProductFormRequest`: `multi_qty_shipping_enabled` boolean; `shipping_charges.*.multi_extra_cost` nullable numeric min:0
- [ ] 4.2 `ProductUiController::syncShippingCharges` persists `multi_extra_cost`; `formData` exposes it + the flag; free-shipping still wipes the rows
- [ ] 4.3 `catalog/products/form.tsx`: checkbox + a second input per zone (shown only when ticked) + a live worked example (qty 1 / 2 / 3) so the admin sees the real numbers before saving
- [ ] 4.4 Feature test: save with the option on · clearing a rate removes it · unticking keeps the per-unit extras

## Phase 5 — storefront
- [ ] 5.1 `Api\ProductShippingZoneController`: add `multi_extra_per_unit` per zone + `multi_qty_enabled` on the payload
- [ ] 5.2 Feature test: endpoint shape, both with the option on and off
- [ ] 5.3 `lib/types.ts` + `components/CheckoutForm.tsx`: the estimate uses `extra + multi × (qty − 1)` when enabled, recomputing as the quantity stepper moves
- [ ] 5.4 **The number shown must equal the number charged** — the storefront reads the same three figures the server does, from the same endpoint

## Phase 6 — gates
- [ ] 6.1 Pest green (chunked, `-d memory_limit=1G`)
- [ ] 6.2 Pint · Larastan max clean
- [ ] 6.3 Admin `types:check` + `lint:check`; storefront `npm run build` (Next 16 type-checks on build)

## Phase 7 — deploy + verify
- [ ] 7.1 Commit + push
- [ ] 7.2 **Owner deploys BOTH** — backend (migrations) **and** frontend (the storefront changed this time)
- [ ] 7.3 Admin: edit a chair → tick the option → Inside Dhaka extra ৳20, additional ৳10 → save
- [ ] 7.4 Storefront: open the chair's checkout, pick Inside Dhaka, step the quantity 1 → 2 → 3 and watch delivery go ৳100 → ৳110 → ৳120
- [ ] 7.5 Place the 3-chair order and confirm the SSLCommerz page asks for the total that was shown
- [ ] 7.6 Admin order detail + invoice PDF show the same ৳120 delivery

## Phase 8 — archive
- [ ] 8.1 Sync the delta spec into `openspec/specs/product-shipping-charges/`
- [ ] 8.2 Archive to `openspec/changes/archive/2026-07-14-multi-qty-shipping-rate/`
