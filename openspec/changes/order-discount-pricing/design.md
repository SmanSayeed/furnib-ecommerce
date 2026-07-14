# Design — order-discount-pricing

## The one rule

```
effectiveDiscount(product) = discount_price  IF  discount_price !== null AND discount_price < price
                           = null            OTHERWISE

effectivePrice(product)    = effectiveDiscount(product) ?? price
```

Everything else in this change is a consequence of that rule.

Two deliberate properties:

- **It can only ever lower the price, never raise it.** `discount_price >= price` is ignored. This is what makes it safe to turn on against existing production data, where `Catalog\UpdateProductRequest` (missing `lt:price`) may already have let a bad row through.
- **It is evaluated at placement time, from the DB, inside the `lockForUpdate()` transaction** — not from anything the client sends. The client still sends only `{product_id, qty}` (`StoreOrderRequest` explicitly accepts no money). Server authority is preserved exactly as it is today.

## Why `< price` and not `> 0 && < price`

A `discount_price` of **0** is a legitimate, validated input: `ProductFormRequest:45` is `['nullable','numeric','min:0','lt:price']` — the admin can deliberately make a product free (a giveaway / bundled item). Guarding with `> 0` would silently charge the *full* price for a product the admin explicitly set to free — a worse bug than the one we are fixing.

The zero-total edge is already handled downstream: `PaymentAmount::for()` floors at 0 and, per its own docblock, *"the init endpoint rejects a zero charge (nothing to pay)"*. A wholly-free order therefore simply cannot be sent to the gateway; it stays `unpaid` and the admin settles it manually. That is correct behaviour, and it is covered by a scenario below.

## Where the price is resolved

Exactly one place, and it stays that way:

`app/Actions/Orders/PlaceOrder.php:73`
```php
- $priceMinor = $product->price->toMinor();
+ $priceMinor = $product->effectivePrice()->toMinor();
```

Everything downstream is already derived from `$priceMinor` / `$lineMinor` and needs **no change**:

| Derived value | Line | Follows automatically |
|---|---|---|
| `$lineMinor` | `:74` | ✅ |
| `$subtotalMinor` | `:75` | ✅ |
| advance (full / % / fixed) | `:79-85` via `AdvancePayment::forLine($lineMinor, …)` | ✅ |
| `order_items.price` / `line_total` | `:103-110` | ✅ |
| `orders.total` | `:163` (`subtotal + shipping`) | ✅ |
| `orders.advance_amount` | `:175` | ✅ |
| SSLCommerz payable | `PaymentAmount::for()` reads `orders.total` | ✅ |
| invoice / SMS / pay link | all read the order row | ✅ |

**Shipping is untouched.** `shippingMinor` (`:130-148`) is computed from the zone base and the per-product per-zone extra — it has no relationship to the product price and must not acquire one.

## The line snapshot

`order_items` currently records `price / qty / line_total` only. Once placed, an order cannot tell you a discount happened. We add:

| column | type | value |
|---|---|---|
| `original_price` | `unsignedBigInteger` **nullable**, paisa | the regular `price` at order time — **only when a discount applied**; `NULL` otherwise |
| `discount_amount` | `unsignedBigInteger` NOT NULL default `0`, paisa | `(original_price − price) × qty` |

`NULL` (not `= price`) for the undiscounted case is deliberate: it makes "was this line discounted?" a single non-null check, and it keeps every existing row valid without a backfill.

Invariant, asserted in tests:
```
price ≤ original_price  (when original_price is not null)
discount_amount = (original_price − price) × qty
line_total      = price × qty
```

## Why `ProductResource` must gate the discount

The storefront does `discount_price ?? price` in six files. If the API keeps emitting a `discount_price` that the server will *not* honour (e.g. a legacy row where `discount_price >= price`), we recreate the exact same class of bug in the opposite direction — the page advertises a discount, the server bills the regular price.

Rather than editing six frontend files and hoping they stay in sync forever, we make the **API incapable of lying**:

```php
// ProductResource:39
'discount_price' => $this->effectiveDiscount() !== null
    ? $this->money($this->effectiveDiscount())
    : null,
```

Now `discount_price ?? price` on the client is *provably* identical to `effectivePrice()` on the server, for every possible input. No storefront change is needed, and none can drift.

Same reasoning applies to `ProductFeed:42` (Meta rejects a `sale_price` that is not strictly below `price`), `JsonLd:20`, `CapiEvents:74` and `TiktokEvents:68` — all four switch to `effectivePrice()` / `effectiveDiscount()` so the whole system speaks one number.

## Corner cases

| # | Case | Expected |
|---|---|---|
| 1 | `discount_price` is `NULL` | charge `price`. `original_price` = NULL, `discount_amount` = 0. **Byte-identical to today** — this is the regression guard for the 99% path. |
| 2 | `0 < discount_price < price` | charge `discount_price`. `original_price` = `price`, `discount_amount` = `(price − discount) × qty`. |
| 3 | `discount_price = 0`, `price > 0` | charge 0 (admin deliberately made it free). Subtotal contribution 0. If the whole order totals 0, the gateway init rejects it ("nothing to pay") and the order stays `unpaid` for manual settlement. |
| 4 | `discount_price = price` | **ignored** — not a discount. Charge `price`, `original_price` = NULL. |
| 5 | `discount_price > price` (legacy row / API gap) | **ignored** — charge `price`. The price is never raised. Also now blocked at write time by the new `lt:price` on `UpdateProductRequest`. |
| 6 | Mixed cart: one discounted line, one regular | each line resolves independently; subtotal is their sum. |
| 7 | Discount **removed** between page-view and checkout | server charges `price`. Correct — the server is authoritative, and the customer sees the real total on the SSLCommerz page before paying. |
| 8 | Discount **added** between page-view and checkout | server charges the discount. Customer benefits; no complaint possible. |
| 9 | Discount changes **after** the order is placed | the order is unaffected — `order_items.price` is a snapshot. (Existing test `keeps the price snapshot even if the product price later changes` extended to cover `discount_price`.) |
| 10 | Advance = **percentage** | computed on the **discounted** line total. ৳10,000 → ৳8,000 at 30% = ৳2,400 (not ৳3,000). Still rounded half-up to whole taka by `AdvancePayment`. |
| 11 | Advance = **fixed amount** ≥ discounted line total | `AdvancePayment::forLine` already caps the fixed amount at `$lineTotal`. A ৳9,000 fixed advance on an ৳8,000 discounted line becomes ৳8,000, not ৳9,000. |
| 12 | Advance = **full** | = discounted subtotal + shipping. |
| 13 | Advance = **shipping** | unchanged — shipping is price-independent. |
| 14 | Free-shipping product with a discount | shipping stays 0; only the price changes. |
| 15 | `qty > 1` on a discounted line | `line_total = discount × qty`; `discount_amount = (price − discount) × qty`. |
| 16 | Two orders placed concurrently for the same product | unchanged — `lockForUpdate()` already serialises them; the price is read inside the lock. |
| 17 | SSLCommerz `val_id` return | `RecordPayment` compares the returned amount to the *stored* payment amount, which was derived from the discounted total. Verification still passes. No change. |
| 18 | Orders placed **before** this change | untouched. Their `original_price` is NULL and `discount_amount` is 0, which is exactly what a non-discounted line looks like. Historic invoices still render. |

## Rollout & data integrity

1. The migration is **additive and nullable** — zero downtime, no backfill, safe to deploy ahead of the code.
2. **Pre-deploy audit query** (run on production, read-only) — find rows the old `UpdateProductRequest` may have let through:
   ```sql
   SELECT id, sku, title, price, discount_price
   FROM products
   WHERE discount_price IS NOT NULL AND discount_price >= price;
   ```
   Any hit would previously have been *displayed* as a discount by the storefront. After this change they are ignored (price charged) and blocked at write time. The owner should clean them up.
3. **Post-deploy report** — unpaid/pending orders placed *before* the fix whose lines were overcharged:
   ```sql
   SELECT o.order_no, o.status, o.payment_status, oi.title,
          oi.price AS charged, p.discount_price AS should_have_been
   FROM orders o
   JOIN order_items oi ON oi.order_id = o.id
   JOIN products p     ON p.id = oi.product_id
   WHERE o.payment_status <> 'paid'
     AND p.discount_price IS NOT NULL
     AND p.discount_price < p.price
     AND oi.price > p.discount_price;
   ```
   These are **not** auto-corrected. The owner decides per order; the forthcoming `admin-order-discount` change gives them the tool to adjust one.

## Verification

- **Pest** (TDD, red first): unit tests on `Product::effectivePrice()` / `effectiveDiscount()` covering corner cases 1–5; feature tests on `PlaceOrder` covering 2, 3, 6, 9, 10, 11, 12, 15; a feature test asserting `PaymentAmount::for($order, 'full')` on a discounted order returns the discounted payable (17); a regression test that an undiscounted order is unchanged (1).
- **Playwright, against production after deploy**: pick a real discounted product on `furnib.com`, read the displayed discounted price, complete checkout to the SSLCommerz gateway page, and assert the gateway's amount equals the discounted price. This is the acceptance test the owner actually cares about — the screenshot in `ssl-gateway-amount.png` is the current failure.
