## Why

After an order is placed the total is frozen. The only money lever an admin has is the
payment ledger (`RecordManualPayment`), which records a payment or a refund — it moves
`advance_paid`, never `total`. So there is no way to say *"give this customer ৳500 off"* and
have the pay link, the due amount and the invoice all follow. The owner has to either eat it
as a fake "refund" (which lies about what was paid) or tell the customer a number the system
disagrees with.

We need a real, first-class **order-level discount**: an amount + a required note, applied by an
authorized admin, that reduces the order total and flows to every surface that reads the order
row — the SSLCommerz pay link, the customer pay page, the invoice PDF, the admin due figure.

## What Changes

- **`orders.discount`** (paisa, default 0) + **`orders.discount_note`** + **`orders.discount_by`**
  (FK users) — a persisted order-level discount, separate from the per-line product discounts
  that already exist.
- **The total invariant becomes** `total = subtotal − discount + shipping_cost`, floored at 0,
  enforced in **one** place (`RecalculateOrderTotals`) that both the discount action and the
  zone-change recompute call — so they can never disagree.
- **`ApplyOrderDiscount` action** — `orders.manage` gated; sets/updates/clears the discount,
  recomputes the total, re-runs `OrderPaymentReconciler` so paid/partial/unpaid + due stay
  correct.
- **Guards:** reject a discount greater than the subtotal (never a negative total); reject when
  the order is already **paid** (that is a refund decision, not a discount); **hard-block** when a
  courier consignment already exists, because the COD amount was snapshotted with the courier at
  booking — changing the total silently would make the courier collect the wrong cash.
- **Pay link & gateway auto-adjust for free** — `PaymentAmount::for()` reads `orders.total` live;
  nothing to change there, pinned by a test.
- **Invoice** gains an "Order Discount" line (only when non-zero); the arithmetic still adds up.
- **Admin order detail** gets an "Apply / update discount" card showing the recomputed
  Total / Paid / Due.

## Impact

- Affected specs: `admin-order-ops` (new capability in this repo's spec set).
- DB: one additive migration (nullable/defaulted columns) — no backfill, existing orders read
  `discount = 0` and are byte-identical to today.
- Money: the discount is entered in **whole taka** and normalized to paisa, matching the manual
  payment convention.
- Security: the discount is server-authoritative and permission-gated; the client sends a taka
  amount + note only, never a total.
