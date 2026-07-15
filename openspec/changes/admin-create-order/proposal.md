## Why

Orders can only be born on the public storefront. Staff cannot place an order for a customer who
phones in, walks in, or orders over WhatsApp — there is no admin create screen, no route, no
action. The owner asked for a "Create order" button on the orders list that produces a real order
with a pay link.

## What Changes

- **Generalize `PlaceOrder`** with a `source` (`storefront` | `admin`) and `created_by`. When —
  and only when — `source = admin`, three staff-only levers unlock: a per-line **unit price
  override**, an order-level **discount**, and a **manual shipping** figure. The gate lives
  **inside `PlaceOrder`**, so a storefront payload that smuggles any of these is still ignored —
  the public checkout stays byte-identical. This is the one spot a price-tampering hole could
  enter, and a test pins it shut.
- **`orders.source`** (default `storefront`) + **`orders.created_by`** (FK users).
- **`CreateAdminOrder` action** wraps `PlaceOrder`: after placement it optionally records the
  advance as a manual ledger credit (so payment status is *derived*, never set), optionally
  confirms the order (running the same observer → courier auto-book), and optionally sends the
  pay-link SMS.
- **Routes** (`orders.manage`): `GET /admin/orders/create`, `POST /admin/orders`,
  `GET /admin/orders/product-search`.
- **`orders/create.tsx`** — customer, searchable product picker (unit price defaults to the
  effective/discount-aware price, editable), zone + shipping override, discount + note, advance,
  confirm & send-SMS toggles, a live totals summary. Lands on the new order's detail page, which
  already carries the copyable pay link.
- **`+ Create order`** button on the orders list.

## Impact

- Affected specs: `admin-order-ops`.
- DB: one additive migration; existing orders read `source = storefront`.
- Security: price/discount/shipping overrides are gated to `source = admin` **and** the route is
  `orders.manage`; the storefront can never reach them (regression-tested). Money is entered in
  whole taka and normalized server-side; the client never sends a total.
