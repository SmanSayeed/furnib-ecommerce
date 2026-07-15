# Tasks — post-order-admin-discount

## DB
- [ ] Migration: `orders.discount` (unsignedBigInteger, default 0), `orders.discount_note` (string nullable), `orders.discount_by` (FK users nullOnDelete)
- [ ] `Order` model: fillable + `discount` MoneyCast + docblock props

## Domain
- [ ] `Services\Orders\RecalculateOrderTotals::totalMinor(Order)` — the single invariant
- [ ] `UpdateOrderCustomer::recomputeShipping` uses the shared invariant (discount-aware)
- [ ] `Actions\Orders\ApplyOrderDiscount` — guards + recompute + reconcile
- [ ] `Requests\Admin\ApplyOrderDiscountRequest` — taka→paisa, `orders.manage`

## HTTP
- [ ] `OrderController::applyDiscount` + route `PUT /admin/orders/{order}/discount`
- [ ] `show()` payload exposes `discount`

## Views
- [ ] Invoice `_document.blade.php`: conditional "Order Discount" row
- [ ] `orders/show.tsx`: apply-discount card + display

## Tests (Pest, TDD)
- [ ] discount reduces total & due
- [ ] `PaymentAmount::for` returns the reduced payable
- [ ] clear (0) restores total
- [ ] replace, not stack
- [ ] discount > subtotal rejected
- [ ] paid order rejected
- [ ] shipment exists → blocked
- [ ] total below paid rejected
- [ ] partial order reconciles
- [ ] non-`orders.manage` forbidden
