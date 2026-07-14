## Why

A product's extra delivery charge is currently a single **per-unit** figure: `extra_cost × qty`. A chair with a ৳20 Inside-Dhaka extra therefore costs ৳20 for one, ৳40 for two, ৳60 for three.

That is wrong for furniture. One van goes out either way — the second and third chair genuinely cost less to carry than the first. The owner needs a **cheaper rate for each additional unit**, set per product and per zone, on top of the figures that already exist.

The rule, in the owner's own algebra:

```
qty 1 → base + additional + (1 − 1) × multi
qty 2 → base + additional + (2 − 1) × multi
qty 3 → base + additional + (3 − 1) × multi
```

Worked, with a chair Inside Dhaka (zone base ৳80, additional ৳20, multi ৳10):

```
qty 1 → 80 + 20 + 10×0 = ৳100
qty 2 → 80 + 20 + 10×1 = ৳110
qty 3 → 80 + 20 + 10×2 = ৳120
qty 5 → 80 + 20 + 10×4 = ৳140
```

Today those same five chairs would be charged `80 + 20×5 = ৳180`.

Note what the rule does to the **existing** `extra_cost` field: with the feature on, it stops being "per unit" and becomes **the charge for the first unit**, once per line. Every additional unit is charged the multi rate instead.

## What Changes

- **`products.multi_qty_shipping_enabled`** — a per-product checkbox: *"Cheaper delivery for each additional unit."* Off by default, so **every existing product keeps today's behaviour exactly**.
- **`product_shipping_charges.multi_extra_cost`** — a second, per-zone figure alongside the existing `extra_cost`. Nullable: a zone left blank falls back to charging the single rate per unit, as today.
- **The rule:**

  ```
  extraForLine = multiEnabled && multiRate !== null
                   ? extra + multiRate × (qty − 1)      // first unit full, rest discounted
                   : extra × qty                        // today's behaviour, untouched

  shipping     = (any chargeable line ? zone.base : 0) + Σ extraForLine
  ```

  At `qty = 1` both branches give `extra`, so turning the feature on never changes a single-item order. The zone base is still charged **once per order**, not per product — unchanged.
- **Admin product form** — the existing "Shipping charges" grid gains the checkbox and, when ticked, a second input per zone (*"each additional unit"*), plus a live worked example showing what qty 1 / 2 / 3 will actually cost. The first input is relabelled so its meaning is unambiguous once the feature is on.
- **Storefront checkout auto-adjusts.** `GET /products/{slug}/shipping-zones` also returns each zone's `multi_extra_per_unit` and the product's `multi_qty_enabled`, so the quantity stepper recomputes delivery live — matching exactly what `PlaceOrder` will charge.

## Non-goals

- **Arbitrary quantity tiers** (`min_qty: 1 / 3 / 10 …`). The owner asked for one threshold — the first unit vs the rest. A full tier table is a bigger form for a case nobody has. The schema leaves room to add `min_qty` later without changing the formula's shape.
- **Cross-product tiers** ("any 3 items in the cart"). The rate is per product line, as the extra always has been.
- **Changing how the zone base is charged.** Still once per order.

## Capabilities

### Modified Capabilities
- `product-shipping-charges`: the per-product per-zone extra becomes quantity-aware — the first unit pays the full extra, each additional unit pays a cheaper rate.
- `checkout-shipping`: the storefront's live delivery estimate honours the additional-unit rate.

## Impact

- **DB**: `products.multi_qty_shipping_enabled` (boolean, default false); `product_shipping_charges.multi_extra_cost` (unsignedBigInteger, nullable, paisa). Both additive — existing rows keep today's behaviour.
- **Backend**: `Product::extraMinorFor()` (replaces the per-unit-only `extraPerUnitMinorFor`, which cannot express this); `ShippingCalculator`; `ProductShippingCharge`; `Admin\ProductFormRequest` + `ProductUiController` (validation + sync + form payload); `Api\ProductShippingZoneController`.
- **Admin UI**: `catalog/products/form.tsx`.
- **Storefront**: `lib/types.ts`, `components/CheckoutForm.tsx` — the estimate mirrors the server formula.
- **Risk**: low. Everything is behind a checkbox that defaults to off, and the existing `PlaceOrderTest` / `FreeShippingProductTest` / `ProductShippingZonesTest` stay green **untouched** — that is the proof nothing regressed.
