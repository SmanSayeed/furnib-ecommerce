## Why

Two gaps the owner hit while using the admin order tools:

1. **Manual payments don't record the channel.** When staff record an advance or an offline
   payment (order detail "adjust payment", or the create-order advance), there was no way to say
   it came via **bKash / Nagad / Rocket / bank / cash / other** and to attach the **transaction
   id**. The ledger only had an amount + free note, so reconciling against a bKash statement was
   guesswork.
2. **A specific order's delivery charge can't be hand-set.** Shipping is derived from the zone,
   but a single order sometimes needs a different figure (negotiated free delivery, a remote-area
   surcharge, a correction). The create page had a shipping override, but an **existing** order
   had no direct edit — only a zone change (which re-derives, not sets).

## What Changes

- **`payments.method`** — the channel a manual payment moved through. Every manual entry now
  requires a `method` (bKash/Nagad/Rocket/bank/cash/other); the transaction id / bank ref goes in
  the existing required note. Shown in the payment ledger. Gateway (SSLCommerz) rows keep
  `method = null`.
- **Create-order advance** captures the same `method` + note and passes them to the ledger entry.
- **`PUT /admin/orders/{order}/shipping`** (`orders.manage`) — a manual delivery-charge override
  on an existing order via `UpdateOrderShipping`. It recomputes the total through the shared
  `RecalculateOrderTotals` (so a discount is preserved) and re-runs the reconciler, and is guarded
  on exactly the same edges as a discount: **paid order → rejected**, **already-booked order →
  blocked** (courier holds the COD), **total below what was paid → rejected**. A later zone change
  re-derives shipping and replaces the override (documented).

## Impact

- Affected specs: `admin-order-ops`.
- DB: one additive nullable column (`payments.method`) — existing rows read null.
- Money: the override + advance amounts are whole taka, normalized server-side; the pay link,
  invoice and due all follow the recomputed `orders.total` for free.
- Breaking for callers of the manual-payment endpoint: `method` is now required (the admin UI
  provides it; tests updated).
