# Design — post-order-admin-discount

## The invariant, in one place

```
total = max(0, subtotal − discount + shipping_cost)
```

`subtotal` is already **net of per-line product discounts** (snapshotted at placement). The new
`orders.discount` is an **order-level** reduction on top of that — a goodwill / negotiation
discount the admin grants after the fact. The two are different money and both show on the
invoice.

`RecalculateOrderTotals::totalMinor(Order)` is the single implementation. `ApplyOrderDiscount`
and `UpdateOrderCustomer` (zone change) both call it — the second one previously did
`subtotal + shipping` inline, which would have ignored a discount. Now neither can drift.

## Why "discount ≤ subtotal", not "≤ subtotal + shipping"

A discount reduces the **goods** price, not the delivery fee. Capping at `subtotal` keeps the
meaning clean (you can zero out the products but the customer still pays to have them shipped)
and guarantees a non-negative total without a second clamp. The `max(0, …)` in the invariant is
belt-and-braces.

## Guards (the sharp edges)

| Case | Decision | Why |
|---|---|---|
| `discount > subtotal` | reject | would imply the shop pays the customer for the goods |
| order already `paid` | reject | reducing the total after full payment is a **refund**, a separate deliberate action with its own ledger row |
| a shipment/consignment exists | **hard block** | the courier COD was snapshotted at booking (`CreateConsignment`); a silent total change desyncs the cash the rider collects. v1 blocks with a clear message: cancel & re-book to change the money |
| `discount = 0` | allowed | clears a previous discount — total returns to `subtotal + shipping` |
| partial payment exists, new total ≥ paid | allowed | due shrinks, reconciler flips paid/partial/unpaid correctly |
| new total < already paid | reject | would owe the customer money — refund first |

## Where the money flows (nothing else to touch)

Same story as the discount-pricing and multi-qty changes: the number lives on `orders.total`,
and every surface reads that row.

| Surface | Reads | Effect of a discount |
|---|---|---|
| SSLCommerz pay link / gateway | `PaymentAmount::for()` → `orders.total` | charges the reduced amount, live |
| Customer pay page summary | `orders.total`, `advance_paid` | shows reduced due |
| Invoice PDF | `orders.total` + new discount row | prints the discount + correct total |
| Admin due | `total − advance_paid` | correct |
| Reconciler | `orders.total` | re-derives paid/partial/unpaid |

So the change lands in `ApplyOrderDiscount` + `RecalculateOrderTotals` (truth) and the invoice
view + show page (display), and propagates everywhere else for free. A test pins
`PaymentAmount::for()` on a discounted order to the reduced payable.

## Corner cases

| # | Case | Expected |
|---|---|---|
| 1 | No discount (every existing order) | `discount = 0`, total unchanged — identical to today |
| 2 | Discount then clear (set 0) | total returns to `subtotal + shipping` |
| 3 | Re-apply a different discount | replaces, not stacks — the column holds the current discount, not a running sum |
| 4 | Discount == subtotal | total = shipping only; allowed |
| 5 | Discount on a partially-paid order | due shrinks; if new total ≤ paid, reconciler marks it paid |
| 6 | Zone changed later on a discounted order | shipping recomputes, discount preserved, total via the same invariant |
| 7 | Invoice with discount = 0 | no "Order Discount" row rendered |
