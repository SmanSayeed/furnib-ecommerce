## Why

**Every discounted product is being charged at its regular price.** A customer sees ৳8,000 on the product page, places the order, and SSLCommerz asks for ৳10,000. The order row, the invoice, the SMS and the pay link all carry the wrong number.

The cause is a single line. `PlaceOrder` — the *only* place an order line's unit price is resolved — reads `products.price` and never looks at `products.discount_price`:

```php
// app/Actions/Orders/PlaceOrder.php:73
$priceMinor = $product->price->toMinor();
```

`discount_price` appears **zero times** anywhere under `app/Actions/`, `app/Services/Orders/` or `app/Support/Payments/`.

Everything *else* in the codebase already resolves the effective price correctly — the storefront (`CheckoutForm.tsx:25`, `ProductActions.tsx:20`, `ProductRow.tsx:79`, `HeaderSearch.tsx:123`, `product/[slug]/page.tsx:41`, `lib/whatsapp.ts:36,56`), the SEO JSON-LD (`JsonLd.php:20`), the Meta CAPI (`CapiEvents.php:74`) and the TikTok events (`TiktokEvents.php:68`) all use `discount_price ?? price`. Order placement is the lone outlier, and it is the one that touches money.

The payment path is *not* at fault and needs no change: the client never sends an amount (`StoreOrderRequest`), and SSLCommerz recomputes from `orders.total` server-side (`PaymentAmount::for()`). That is precisely why the bug is total — the wrong number is baked into `orders.total` at placement and everything downstream faithfully repeats it.

Two supporting defects found while tracing this:

1. **`Catalog\UpdateProductRequest:36` is missing the `lt:price` rule** that `StoreProductRequest:32` and `Admin\ProductFormRequest:45` both have. The API can therefore persist a `discount_price` **greater than or equal to** `price` — which, once we start honouring the discount, would *raise* the customer's price.
2. **The discount is not recorded anywhere.** `order_items` stores only `price / qty / line_total`, so once an order is placed there is no way to know a discount was applied, what the original price was, or how much the customer saved. Invoices and reporting can never show it.

## What Changes

- **`Product::effectiveDiscount(): ?Money` and `Product::effectivePrice(): Money`** become the single source of truth for "what does this product actually cost". A discount is *effective* only when it is non-null and **strictly less than** `price`. A stored discount that is `>= price` is ignored (never raises the price) — a defensive guard against legacy rows and the `UpdateProductRequest` gap.
- **`PlaceOrder` charges `effectivePrice()`.** Subtotal, line totals, order total, the advance amount (percentage / fixed / full) and therefore the SSLCommerz payable all follow automatically.
- **`order_items` snapshots the discount**: new nullable `original_price` (the regular price at order time, only set when a discount was applied) and `discount_amount` (`(original − price) × qty`, default 0). The savings become permanent, auditable data.
- **`ProductResource` stops advertising an ineffective discount** — it emits `discount_price` only when `effectiveDiscount()` is non-null. This makes the storefront's `discount_price ?? price` automatically agree with the server for every input, without touching six frontend files.
- **`Catalog\UpdateProductRequest` gains `lt:price`**, closing the write-side hole.
- **`ProductFeed`** emits `sale_price` from `effectiveDiscount()` (Meta rejects `sale_price >= price`).
- **Admin order detail + invoice** show the original price struck through and the amount saved when a line was discounted.

## Non-goals

- **Order-level / admin-applied discounts** (post-order "give this customer ৳500 off") — that is a separate change (`admin-order-discount`), which will add `orders.discount`. This change is only about the *product's own* discount at placement time.
- **Coupons.** No coupon system exists and none is added here.
- **Retro-fixing existing orders.** Historic orders keep their recorded totals — rewriting money on placed orders is never safe. A read-only report of affected unpaid orders is produced instead, so the owner can decide per order.
- **Time-windowed discounts** (start/end dates). `discount_price` is a plain nullable column; scheduling is out of scope.

## Capabilities

### New Capabilities
- `order-pricing`: server-authoritative resolution of a product's effective unit price (discount-aware) at order placement, and the permanent snapshot of the discount on each order line.

### Modified Capabilities
- `product-admin-listing`: `discount_price` must be strictly below `price` on **every** write path (admin form and API), not just two of the three.

## Impact

- **DB**: one migration — `order_items.original_price` (nullable, paisa) and `order_items.discount_amount` (paisa, default 0).
- **Backend**: `Product` (2 new methods), `PlaceOrder` (price resolution + line snapshot), `OrderItem` (fillable + casts), `ProductResource` (discount gating), `Catalog\UpdateProductRequest` (validation), `ProductFeed` (sale_price), `Admin\OrderController@show` (expose original price), `resources/views/invoices/_document.blade.php` (savings row).
- **Admin UI**: `resources/js/pages/orders/show.tsx` (struck-through original price on discounted lines).
- **Storefront**: **no code change required** — `discount_price ?? price` keeps working, and now agrees with the server by construction.
- **Depends on**: `Money`, `MoneyCast`, `AdvancePayment`, `PaymentAmount` — all unchanged.
- **Risk**: low and well-bounded. The only behavioural change on the money path is that discounted products now bill the advertised price. Regular-priced products are byte-for-byte unaffected (`effectivePrice()` returns `price` when there is no discount).
