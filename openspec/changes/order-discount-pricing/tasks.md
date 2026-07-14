# Tasks — order-discount-pricing

## Phase 1 — RED: prove the bug
- [ ] 1.1 `tests/Unit/ProductEffectivePriceTest.php` — corner cases 1–5 (null / valid / zero / equal / above). **Fails: methods don't exist.**
- [ ] 1.2 `tests/Feature/Orders/OrderDiscountPricingTest.php` — discounted product ordered → `order_items.price` / `subtotal` / `total` are the discounted amount. **Fails: charges regular price. This is the reported bug, now pinned by a test.**

## Phase 2 — GREEN: the model rule
- [ ] 2.1 `Product::effectiveDiscount(): ?Money` — non-null AND `< price`, else null
- [ ] 2.2 `Product::effectivePrice(): Money` — `effectiveDiscount() ?? price`
- [ ] 2.3 Phase-1 unit tests green

## Phase 3 — GREEN: order placement
- [ ] 3.1 Migration: `order_items.original_price` (unsignedBigInteger nullable, after `price`) + `order_items.discount_amount` (unsignedBigInteger, default 0)
- [ ] 3.2 `OrderItem` — add both to `$fillable`; `original_price` + `discount_amount` → `MoneyCast`; update the class docblock
- [ ] 3.3 `PlaceOrder:73` → `$product->effectivePrice()->toMinor()`
- [ ] 3.4 `PlaceOrder:103-110` → snapshot `original_price` (regular price, only when discounted) and `discount_amount` = `(original − effective) × qty`
- [ ] 3.5 Feature tests green: discounted price charged · qty multiplies · mixed cart · shipping unaffected · snapshot survives a later discount change · line snapshot fields correct
- [ ] 3.6 Advance tests: percentage on discounted line (৳2,400 not ৳3,000) · fixed capped at the discounted line total · full = discounted subtotal + shipping
- [ ] 3.7 **Regression guard**: an undiscounted order produces byte-identical totals to before (existing `PlaceOrderTest` must stay green untouched)

## Phase 4 — GREEN: payment path (assert, don't change)
- [ ] 4.1 Feature test: `PaymentAmount::for($order, 'full')` on a discounted order returns the discounted payable
- [ ] 4.2 Feature test: SSLCommerz init for a discounted order sends the discounted `total_amount`
- [ ] 4.3 Confirm a zero-total order (discount_price = 0, no shipping) is rejected by the init endpoint with "nothing to pay" — no code change expected, just pinned

## Phase 5 — close the write-side hole
- [ ] 5.1 `Catalog\UpdateProductRequest:36` → add `lt:price` (matching `StoreProductRequest:32` and `Admin\ProductFormRequest:45`)
- [ ] 5.2 Feature tests: API update rejects `discount_price >= price`, accepts a valid discount

## Phase 6 — stop the API advertising a lie
- [ ] 6.1 `ProductResource:39` → emit `discount_price` only when `effectiveDiscount()` is non-null
- [ ] 6.2 Feature test: product API returns `discount_price: null` for an ineffective discount, and the value for a valid one
- [ ] 6.3 `JsonLd:20`, `CapiEvents:74`, `TiktokEvents:68` → `effectivePrice()` (they currently do `discount_price ?? price`, which is wrong for the `>= price` edge)
- [ ] 6.4 `ProductFeed:42` → `sale_price` from `effectiveDiscount()`; test that an ineffective discount yields an empty `sale_price`
- [ ] 6.5 **Storefront: no change.** Verify `discount_price ?? price` still resolves identically now the API gates the field.

## Phase 7 — surface the saving
- [ ] 7.1 `Admin\OrderController@show` → include `original_price` / `discount_amount` per item
- [ ] 7.2 `resources/js/pages/orders/show.tsx` → struck-through original price + "saved ৳X" on discounted lines
- [ ] 7.3 `resources/views/invoices/_document.blade.php` → same on the invoice PDF
- [ ] 7.4 Feature test: invoice PDF for a discounted order renders without error and contains the saving

## Phase 8 — gates
- [ ] 8.1 Pest green (run in chunks, `-d memory_limit=1G` — the full suite OOMs on dompdf)
- [ ] 8.2 Larastan max clean
- [ ] 8.3 Pint clean
- [ ] 8.4 `npm run types:check` + `npm run lint:check` (admin)
- [ ] 8.5 Pre-deploy audit query run on production: `SELECT id, sku, price, discount_price FROM products WHERE discount_price IS NOT NULL AND discount_price >= price;` — report any hits to the owner

## Phase 9 — deploy + verify in production
- [ ] 9.1 Commit + push to `master` (feature branch `fix/order-discount-pricing`, merged after gates)
- [ ] 9.2 **Owner deploys**: EasyPanel → `furnib` → `backend` → Deploy (migration runs automatically via `entrypoint.sh`). No frontend redeploy needed — the storefront is unchanged.
- [ ] 9.3 **Playwright against `https://furnib.com`**: open a real discounted product, record the displayed discounted price, complete checkout through to the SSLCommerz gateway page, assert the gateway amount equals the discounted price (the current failure is captured in `ssl-gateway-amount.png`)
- [ ] 9.4 Playwright against `https://admin.furnib.com`: the new order shows the discounted line with the struck-through original and the saving; the invoice PDF matches
- [ ] 9.5 Post-deploy report: list unpaid orders placed before the fix that were overcharged, hand to the owner (do NOT auto-correct)

## Phase 10 — archive
- [ ] 10.1 Sync the delta spec into `openspec/specs/order-pricing/`
- [ ] 10.2 Archive the change to `openspec/changes/archive/2026-07-14-order-discount-pricing/`
