## ADDED Requirements

### Requirement: Order-level admin discount
The system SHALL let an authorized admin (`orders.manage`) apply a whole-taka order-level
discount with a required note to an order, reducing its total. The discount SHALL be stored on
the order (`discount`, `discount_note`, `discount_by`) separately from any per-line product
discounts.

The order total SHALL be `max(0, subtotal − discount + shipping_cost)`, recomputed through a
single shared calculation, and the payment status (paid / partial / unpaid) and due SHALL be
re-derived from the payment ledger after the total changes.

#### Scenario: A discount reduces the total and the due
- **WHEN** an admin applies a ৳500 discount to an unpaid order with subtotal ৳10,000 and shipping ৳100
- **THEN** the order total becomes ৳9,600, the due becomes ৳9,600, and the discount and note are recorded with the acting admin

#### Scenario: The pay link charges the reduced amount
- **WHEN** the SSLCommerz payable is resolved for that discounted order
- **THEN** `PaymentAmount::for(full)` returns ৳9,600 — the reduced total, not the original

#### Scenario: Clearing the discount restores the total
- **WHEN** an admin sets the discount back to ৳0
- **THEN** the total returns to `subtotal + shipping` (৳10,100) and the discount note is cleared

#### Scenario: A new discount replaces the old one
- **WHEN** an order already has a ৳500 discount and an admin applies ৳800
- **THEN** the discount is ৳800 (replaced, not ৳1,300) and the total reflects ৳800 off

#### Scenario: Discount cannot exceed the subtotal
- **WHEN** an admin tries to discount more than the order's subtotal
- **THEN** the request is rejected and the total is unchanged

#### Scenario: A paid order cannot be discounted
- **WHEN** an admin tries to discount an order whose payment status is `paid`
- **THEN** the request is rejected with a message to record a refund instead

#### Scenario: A booked order blocks the discount
- **WHEN** an admin tries to discount an order that already has a courier consignment
- **THEN** the request is rejected with a message to cancel and re-book, because the courier holds the old COD amount

#### Scenario: The discount cannot drop the total below what was already paid
- **WHEN** an order has ৳2,000 already paid and a discount would make the total ৳1,500
- **THEN** the request is rejected with a message to record a refund first

#### Scenario: A partially-paid order reconciles after a discount
- **WHEN** an order with ৳3,000 paid and total ৳10,100 receives a discount that makes the total ৳9,600
- **THEN** the total is ৳9,600, the due is ৳6,600, and the payment status stays `partial`

### Requirement: The invoice reflects an order-level discount
The invoice PDF SHALL show the order-level discount as its own line when it is non-zero, and the
printed subtotal, discount(s), delivery and total SHALL add up. When the discount is zero no
order-discount line SHALL be rendered.

#### Scenario: Invoice shows the order discount
- **WHEN** an order with a ৳500 order-level discount is rendered to an invoice
- **THEN** the invoice shows an "Order Discount" line of 500Tk. and the Total equals `gross subtotal − item discounts − order discount + delivery`

#### Scenario: Invoice omits a zero order discount
- **WHEN** an order with no order-level discount is rendered
- **THEN** no "Order Discount" line appears
