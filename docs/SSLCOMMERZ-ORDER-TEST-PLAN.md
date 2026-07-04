# SSLCommerz Order Test Plan — advance & shipping cases

Verifies that the **amount charged at the gateway** and the **COD due** are correct
for every advance-payment configuration, end-to-end (storefront checkout →
SSLCommerz session → return → invoice/label).

**Money rule:** all displayed/charged values are whole taka (no poysha). Advance is
rounded to the nearest whole taka, half-up (e.g. ৳1470.50 → ৳1471).

## Formulae (server = source of truth)

- `subtotal = Σ (unit_price × qty)` where `unit_price = discount_price ?? price`
- `zone_shipping = zone.base + Σ (product.extra_per_unit_for_zone × qty)` ← product-wise
- `total = subtotal + zone_shipping`
- `advance` by product config:
  - **none** → 0 (pure COD)
  - **full** → `total` (subtotal + shipping)
  - **partial / percentage(p)** → `round(subtotal × p / 100)` to whole taka
  - **partial / amount(a)** → `min(a, subtotal)` (a stored as whole-taka paisa)
  - **partial / shipping** → `zone_shipping` (requires a zone)
- `gateway_charge (advance init) = advance`
- `COD due = total − advance_paid`

## Test matrix

Example product price **৳2,941** (no discount), zone **Inside Dhaka base ৳80**,
**Outside Dhaka base ৳150**, unless a case overrides. Amounts below are for
**qty = 1** unless noted.

| # | Config | Zone | Qty | Subtotal | Shipping | Total | Advance (pay now) | COD due |
|---|--------|------|-----|----------|----------|-------|-------------------|---------|
| 1 | No advance (COD) | Inside ৳80 | 1 | 2,941 | 80 | 3,021 | 0 | 3,021 |
| 2 | **Full** | Inside ৳80 | 1 | 2,941 | 80 | 3,021 | **3,021** | 0 |
| 3 | **Full** | Outside ৳150 | 2 | 5,882 | 150 | 6,032 | **6,032** | 0 |
| 4 | Partial **percentage 50%** | Inside ৳80 | 1 | 2,941 | 80 | 3,021 | **1,471** (round ↑ from 1470.5) | 1,550 |
| 5 | Partial **percentage 30%** | Outside ৳150 | 2 | 5,882 | 150 | 6,032 | **1,765** (round from 1764.6) | 4,267 |
| 6 | Partial **fixed amount ৳1,000** | Inside ৳80 | 1 | 2,941 | 80 | 3,021 | **1,000** | 2,021 |
| 7 | Partial **fixed amount ৳5,000** (> subtotal) | Inside ৳80 | 1 | 2,941 | 80 | 3,021 | **2,941** (capped at subtotal) | 80 |
| 8 | Partial **shipping** | Inside ৳80 | 1 | 2,941 | 80 | 3,021 | **80** | 2,941 |
| 9 | Partial **shipping** | Outside ৳150 | 1 | 2,941 | 150 | 3,091 | **150** | 2,941 |
| 10 | **Product-wise shipping** (extra ৳70/unit inside) + no advance | Inside ৳80 | 2 | 5,882 | 80+70×2=220 | 6,102 | 0 | 6,102 |
| 11 | Product-wise shipping + **partial shipping** advance | Inside ৳80+৳70/u | 2 | 5,882 | 220 | 6,102 | **220** | 5,882 |
| 12 | Product-wise shipping + **full** advance | Outside ৳150+৳150/u | 2 | 5,882 | 150+150×2=450 | 6,332 | **6,332** | 0 |

### Edge / negative cases
- **E1** Partial shipping with **no zone selected** → checkout blocks ("select a delivery area"); gateway never opens.
- **E2** Full advance with **no zone** → advance = subtotal (shipping 0).
- **E3** Percentage that lands on .5 → rounds **up** (half-up), never poysha.
- **E4** Gateway **cancel** → order stays `pending`/`unpaid`, a `cancelled` Payment row with auto note "Cancelled by customer at the payment gateway.", COD still available.
- **E5** Gateway **fail** → `failed` Payment row with reason note.
- **E6** Duplicate IPN/return → payment applied **once** (idempotent), advance_paid unchanged on the 2nd hit.
- **E7** Amount tampering — the client cannot set the charge; server resolves `advance_amount`. Posting a different amount has no effect.

### After-payment / ledger cases (admin)
- **L1** Successful advance → `advance_paid` = advance, `payment_status` = partial (or paid if full), order **stays pending** (no auto-confirm).
- **L2** Admin **manual credit** ৳X with note → advance_paid += X, invoice Due drops by X, original gateway row untouched.
- **L3** Admin **manual debit** (refund) ৳Y with note → advance_paid −= Y (floored 0); refund > paid rejected.
- **L4** Invoice shows **Advance Paid** + **Due (COD)**; bulk invoice prints **2 per A4**.
- **L5** Shipping label **COD (collect)** = due (not total); shows "Advance paid …" line.

## Verification points per case
1. **Checkout page** shows correct Subtotal / Shipping / Total / "Pay now online (advance)" / "Cash on delivery (due)".
2. **Place order** → SSLCommerz hosted page shows **amount == advance** (or total for full).
3. (Sandbox only) Complete payment with test card → return to `/checkout/result?status=success`.
4. **Admin order** → payment_status + advance_paid correct, status still pending.
5. **Invoice/label PDF** → Advance Paid + Due correct.

### Sandbox test card (SSLCommerz sandbox ONLY)
VISA `4111 1111 1111 1111`, exp `12/26`, CVV `111`, OTP `111111`.

> ⚠️ **Never complete a real payment against LIVE gateway keys.** Confirm the server
> is in sandbox before step 3. Read-only checks (1, 2 up to the gateway amount) are
> safe on any environment.
