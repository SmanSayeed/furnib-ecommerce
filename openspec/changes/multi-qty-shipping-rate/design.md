# Design — multi-qty-shipping-rate

## The rule

```
extraForLine(product, zone, qty) =
    enabled && multiRate is set  →  extra + multiRate × (qty − 1)
    otherwise                    →  extra × qty

shipping(order) = (any chargeable line ? zone.base : 0)
                + Σ over chargeable lines  extraForLine(...)
```

- The **zone base** is charged once per order — not per product line. Unchanged.
- At **qty = 1** both branches yield `extra`, so switching the option on can never move a single-item order. That is what makes this safe to enable on a live catalogue.
- `multiRate = NULL` means *not configured* and falls back to per-unit. `multiRate = 0` is a **deliberate** value: the units after the first then ship free. The two are different, which is why the column is nullable rather than defaulted to 0.

Worked, chair Inside Dhaka (base ৳80, extra ৳20, additional ৳10):

| qty | calculation | shipping | (before this change) |
|---|---|---|---|
| 1 | 80 + 20 + 10×0 | **৳100** | ৳100 |
| 2 | 80 + 20 + 10×1 | **৳110** | ৳120 |
| 3 | 80 + 20 + 10×2 | **৳120** | ৳140 |
| 5 | 80 + 20 + 10×4 | **৳140** | ৳180 |

## Why the meaning of `extra_cost` shifts

With the option **off**, `extra_cost` is *"the extra, per unit"*. With it **on**, it becomes *"the extra for the first unit"*. Same column, two readings.

That is not an accident of implementation — it is the shape of the owner's rule (`base + additional + multi × (qty − 1)`), where `additional` is paid once. Rather than add a third column that would be blank in 99% of products, the admin form **relabels the field** as the checkbox is ticked:

- unticked → *"Extra — per unit"*
- ticked → *"Extra — first unit"* + a second box *"Extra — each additional unit"*

and shows a live worked example (qty 1 / 2 / 3) underneath, so the admin sees the real taka before saving rather than doing the algebra in their head.

## Why the storefront gets a *derived* rate

The obvious API shape would be to send `extra`, `multi` and an `enabled` flag, and let the client branch. That is a second implementation of the rule, in another language, maintained by a different file — exactly the drift that already exists today (`CheckoutForm.tsx:50` duplicates `PlaceOrder`'s formula).

Instead the endpoint sends three **pure numbers** and the client runs **one unconditional formula**:

```
shipping = base + extra_per_unit + multi_extra_per_unit × (qty − 1)
```

where the server computes

```php
extra_per_unit       = extraMinorFor(zone, 1)                        // the first unit
multi_extra_per_unit = extraMinorFor(zone, 2) − extraMinorFor(zone, 1)   // one more unit
```

The trick is that the derivation is **self-correcting**. When the option is off, `extraMinorFor(2) − extraMinorFor(1) = 2·extra − extra = extra`, so the client's formula collapses to `base + extra × qty` on its own. When there is no charge row at all, both terms are 0. The client therefore has **no branch to get wrong**, and any future change to the rule (tiers, weight bands, whatever) changes only the two `extraMinorFor` calls — the client keeps working untouched.

A `multi_qty_enabled` flag is still returned, but only so the UI can *explain* the discount to the shopper. No arithmetic depends on it.

## Where the money flows (and why nothing else needs touching)

Shipping is computed **once**, in `PlaceOrder`, via `ShippingCalculator`, and persisted to `orders.shipping_cost` + `orders.total`. Every other surface reads that row:

| Surface | Reads |
|---|---|
| SSLCommerz pay link / gateway amount | `PaymentAmount::for()` → `orders.total` |
| Invoice PDF | `orders.shipping_cost`, `orders.total` |
| Admin order detail + list | the same row |
| Order SMS (`{total}`, `{due}`) | the same row |
| Courier COD amount | `orders.total − advance_paid` |
| Admin zone-change recompute | the same `ShippingCalculator` |
| Storefront advance preview (`partial_type: shipping`) | `advanceMinor = shippingMinor` |

So the change lands in exactly two places — `Product::extraMinorFor()` (server truth) and `CheckoutForm` (preview) — and propagates everywhere else for free. A test pins the gateway payable to the tiered total, which is what proves it.

## Corner cases

| # | Case | Expected |
|---|---|---|
| 1 | Option off (the default, every existing product) | `extra × qty` — byte-identical to today. The untouched `PlaceOrderTest` is the proof. |
| 2 | Option on, qty 1 | `extra` — unchanged from off |
| 3 | Option on, qty 3 | `extra + multi × 2` |
| 4 | Option on, zone has no multi rate (NULL) | falls back to `extra × qty` for that zone |
| 5 | Option on, multi = 0 | later units ship free: `extra` at any qty |
| 6 | multi > extra | accepted and applied as written — the admin sets prices; we don't second-guess |
| 7 | Product has no charge row for the zone | contributes 0, as today |
| 8 | Free-shipping product (`shipping_charge_allowed = false`) | contributes nothing, and if it is the only line the zone base isn't charged either |
| 9 | Mixed cart: tiered chair + plain table | each line resolves independently; the zone base is added once |
| 10 | No zone selected | shipping 0, as today |
| 11 | Admin changes an order's zone afterwards | recomputed by the same calculator, so the tier applies there too |
| 12 | Option ticked but the rates are then cleared | the form submits blanks, so the rows fall back to per-unit — no stale rate lies in wait |
| 13 | Free shipping turned on | the charge rows are wiped, exactly as today |

## Verification

- **Pest** — the corner cases above, plus: the storefront's own formula run against the endpoint's numbers must land on the same taka as `PlaceOrder` (this is the anti-drift test), and `PaymentAmount::for()` on a tiered order must equal the tiered total (this is the "does SSLCommerz charge the right thing" test).
- **Production, after deploy** — tick the option on a real chair, set ৳20 / ৳10, then on the storefront step the quantity 1 → 2 → 3 and watch delivery read ৳100 → ৳110 → ৳120. Place the order and confirm the SSLCommerz page asks for exactly the total that was shown, then check the admin order and the invoice PDF agree.
